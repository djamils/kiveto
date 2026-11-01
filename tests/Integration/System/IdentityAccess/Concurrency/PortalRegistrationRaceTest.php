<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\IdentityAccess\Concurrency;

use App\Context\ClientPortal\Application\Command\CompletePortalRegistration\CompletePortalRegistration;
use App\Context\ClientPortal\Application\Command\CompletePortalRegistration\CompletePortalRegistrationHandler;
use App\Context\ClientPortal\Application\Command\CompletePortalRegistration\RegistrationOutcome;
use App\Context\ClientPortal\Application\Command\CompletePortalRegistration\RegistrationResult;
use App\Context\ClientPortal\Infrastructure\Adapter\IdentityAccess\CommandBusPortalIdentity;
use App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository\DbalOwnerProfileRepository;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\ValueObject\PhoneNumber;
use App\Shared\Infrastructure\Security\OpaqueTokenHasher;
use App\System\IdentityAccess\Application\Command\RegisterPortalUser\RegisterPortalUser;
use App\System\IdentityAccess\Application\Command\RegisterPortalUser\RegisterPortalUserHandler;
use App\System\IdentityAccess\Application\Service\PortalAccountRegistrar;
use App\System\IdentityAccess\Domain\Exception\PortalAccountExistsException;
use App\System\IdentityAccess\Domain\Otp\OnboardingPurpose;
use App\System\IdentityAccess\Domain\Otp\OtpChallenge;
use App\System\IdentityAccess\Domain\Otp\OtpChallengeId;
use App\System\IdentityAccess\Domain\Otp\OtpPurpose;
use App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Factory\DoctrineUserFactory;
use App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Mapper\UserMapper;
use App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Repository\DbalOtpChallengeRepository;
use App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Repository\DoctrineUserRepository;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\Context\ClientPortal\Support\AnsweringCommandBus;
use App\Tests\Unit\System\IdentityAccess\Support\AccountFakes;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * AC 74, outside the test transaction, on two real connections: two
 * registrations of one phone with two registration grants. PHP runs one
 * connection at a time: the second first runs into the per-phone mutex the
 * first holds (lock wait timeout of 1 s), then, retried once the first
 * committed, finds its account — exactly one account and one profile, the
 * loser answers `account_exists` and keeps its grant unused.
 */
final class PortalRegistrationRaceTest extends KernelTestCase
{
    private const string PHONE  = '+33612347401';
    private const string FIRST  = '01960000-0000-7000-8000-00000000c741';
    private const string SECOND = '01960000-0000-7000-8000-00000000c742';
    private const string USER_1 = '01960000-0000-7000-8000-00000000e741';
    private const string USER_2 = '01960000-0000-7000-8000-00000000e742';
    private const string TERMS  = 'race-terms';

    private Connection $first;
    private Connection $second;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $default = self::getContainer()->get('doctrine.dbal.default_connection');
        \assert($default instanceof Connection);

        $this->first  = DriverManager::getConnection($default->getParams());
        $this->second = DriverManager::getConnection($default->getParams());
        $this->second->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');
        $this->now = new \DateTimeImmutable('2026-11-02 09:00:00', new \DateTimeZone('UTC'));

        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        foreach ([$this->first, $this->second] as $connection) {
            while ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        }

        $this->cleanUp();
        $this->first->close();
        $this->second->close();

        parent::tearDown();
    }

    public function testTwoRegistrationsOfOnePhoneEndOnOneAccount(): void
    {
        $this->grant(self::FIRST, 'token-1');
        $this->grant(self::SECOND, 'token-2');

        $this->first->beginTransaction();
        $this->second->beginTransaction();

        $winner = $this->register($this->first, 'token-1', self::USER_1);
        self::assertSame([RegistrationResult::REGISTERED, self::USER_1], [$winner->result, $winner->userId]);

        try {
            $this->register($this->second, 'token-2', self::USER_2);
            self::fail('The second registration should wait for the per-phone mutex.');
        } catch (LockWaitTimeoutException) {
            $this->addToAssertionCount(1);
        }

        $this->first->commit();

        try {
            $this->register($this->second, 'token-2', self::USER_2);
            self::fail('The phone has an account now.');
        } catch (PortalAccountExistsException) {
            $this->second->rollBack();
        }

        self::assertSame(1, $this->number('SELECT COUNT(*) FROM identity_access__users WHERE phone = ?', [self::PHONE]));
        self::assertSame(1, $this->number('SELECT COUNT(*) FROM client_portal__owner_profiles WHERE user_id IN (?, ?)', [Uuid::fromString(self::USER_1)->toBinary(), Uuid::fromString(self::USER_2)->toBinary()]));
        self::assertSame(1, $this->number('SELECT COUNT(*) FROM identity_access__otp_challenges WHERE id = ? AND onboarding_consumed_at IS NOT NULL', [Uuid::fromString(self::FIRST)->toBinary()]), 'the winner spent its grant');
        self::assertSame(1, $this->number('SELECT COUNT(*) FROM identity_access__otp_challenges WHERE id = ? AND onboarding_consumed_at IS NULL', [Uuid::fromString(self::SECOND)->toBinary()]), 'the loser keeps its grant');
    }

    /** The whole registration of one request, every repository on its connection. */
    private function register(Connection $connection, string $token, string $userId): RegistrationOutcome
    {
        $clock = new FrozenClock($this->now->modify('+1 minute'));
        $fakes = new AccountFakes();
        $uuids = new class($userId) implements UuidGeneratorInterface {
            public function __construct(private readonly string $id)
            {
            }

            public function generate(): string
            {
                return $this->id;
            }
        };
        $users    = $this->users($connection);
        $identity = new RegisterPortalUserHandler(new DbalOtpChallengeRepository($connection), $users, new OpaqueTokenHasher(), new PortalAccountRegistrar($users, $uuids, $fakes->publisher()), $clock);
        $handler  = new CompletePortalRegistrationHandler(
            new CommandBusPortalIdentity(new AnsweringCommandBus(static function (object $command) use ($identity): mixed {
                \assert($command instanceof RegisterPortalUser);

                return $identity($command);
            })),
            new DbalOwnerProfileRepository($connection),
            $clock,
            self::TERMS,
        );

        return $handler(new CompletePortalRegistration($token, 'Sophie', 'Martin', null, self::TERMS));
    }

    private function grant(string $id, string $token): void
    {
        $challenge = OtpChallenge::start(OtpChallengeId::fromString($id), PhoneNumber::fromString(self::PHONE), OtpPurpose::LOGIN, null, hash('sha256', 'code'), $this->now);
        $challenge->verify(hash('sha256', 'code'), $this->now, OtpPurpose::LOGIN);
        $challenge->grantOnboarding(new OpaqueTokenHasher()->hash($token), OnboardingPurpose::REGISTRATION, $this->now);
        new DbalOtpChallengeRepository($this->first)->save($challenge);
    }

    private function users(Connection $connection): DoctrineUserRepository
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);

        return new DoctrineUserRepository(new EntityManager($connection, $em->getConfiguration()), new UserMapper(new DoctrineUserFactory()));
    }

    /**
     * @param list<string> $parameters
     */
    private function number(string $sql, array $parameters): int
    {
        $value = $this->first->fetchOne($sql, $parameters);
        \assert(is_numeric($value));

        return (int) $value;
    }

    private function cleanUp(): void
    {
        $users = [Uuid::fromString(self::USER_1)->toBinary(), Uuid::fromString(self::USER_2)->toBinary()];
        $this->first->executeStatement('DELETE FROM client_portal__owner_profiles WHERE user_id IN (?, ?)', $users);
        $this->first->executeStatement('DELETE FROM identity_access__users WHERE phone = ?', [self::PHONE]);
        $this->first->executeStatement('DELETE FROM identity_access__otp_challenges WHERE phone = ?', [self::PHONE]);
        $this->first->executeStatement('DELETE FROM identity_access__portal_phone_locks WHERE phone = ?', [self::PHONE]);
    }
}
