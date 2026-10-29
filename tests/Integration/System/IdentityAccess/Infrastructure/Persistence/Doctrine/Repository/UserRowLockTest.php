<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Repository;

use App\System\IdentityAccess\Application\Command\RegisterInvitedUser\RegisterInvitedUser;
use App\System\IdentityAccess\Application\Command\RegisterInvitedUser\RegisterInvitedUserHandler;
use App\System\IdentityAccess\Domain\User;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\System\IdentityAccess\Domain\ValueObject\UserStatus;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Factory\DoctrineUserFactory;
use App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Mapper\UserMapper;
use App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Repository\DoctrineUserRepository;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\System\IdentityAccess\Support\AccountFakes;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Two real database connections (outside the test transaction): issuing a
 * link locks the user row, and two invitations of the same address at once
 * end on one account instead of a unique-key error.
 */
final class UserRowLockTest extends KernelTestCase
{
    private const string USER  = '01960000-0000-7000-8000-00000000ad71';
    private const string OTHER = '01960000-0000-7000-8000-00000000ad72';
    private const string EMAIL = 'row.lock@example.com';

    private Connection $first;
    private Connection $second;

    protected function setUp(): void
    {
        $default = self::getContainer()->get('doctrine.dbal.default_connection');
        \assert($default instanceof Connection);

        $this->first  = DriverManager::getConnection($default->getParams());
        $this->second = DriverManager::getConnection($default->getParams());
        $this->second->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');

        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        foreach ([$this->first, $this->second] as $connection) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        }

        $this->cleanUp();
        $this->first->close();
        $this->second->close();

        parent::tearDown();
    }

    public function testASecondLinkIssuanceWaitsForTheFirst(): void
    {
        self::assertTrue($this->users($this->first)->addIfEmailFree($this->invited(self::USER)));

        $this->first->beginTransaction();
        self::assertSame(UserStatus::PENDING, $this->users($this->first)->findByIdForUpdate(UserId::fromString(self::USER))?->status());

        $this->second->beginTransaction();

        try {
            $this->users($this->second)->findByIdForUpdate(UserId::fromString(self::USER));
            self::fail('The second issuance should wait for the first one.');
        } catch (LockWaitTimeoutException) {
            $this->addToAssertionCount(1);
        }
    }

    public function testAConcurrentInvitationOfTheSameAddressJoinsTheAccountCreatedMeanwhile(): void
    {
        // The second request looked first: its snapshot predates the other account.
        $this->second->beginTransaction();
        self::assertNull($this->users($this->second)->findByEmail(self::EMAIL));

        self::assertTrue($this->users($this->first)->addIfEmailFree($this->invited(self::OTHER)));

        $fakes        = new AccountFakes();
        $registration = new RegisterInvitedUserHandler($this->users($this->second), $fakes, new FrozenClock(new \DateTimeImmutable('2026-10-27 10:00:00')), $fakes->publisher())(
            new RegisterInvitedUser(self::EMAIL),
        );
        $this->second->commit();

        self::assertSame([self::OTHER, false], [$registration->userId, $registration->alreadyActive]);
        self::assertSame(1, $this->number('SELECT COUNT(*) FROM identity_access__users WHERE email = ?', [self::EMAIL]));
        self::assertSame([], $fakes->eventNames());
    }

    public function testTheAddressIsReadAgainPastTheSnapshotAndLocked(): void
    {
        $this->second->beginTransaction();
        self::assertNull($this->users($this->second)->findByEmail(self::EMAIL));

        $this->users($this->first)->addIfEmailFree($this->invited(self::OTHER));

        self::assertNull($this->users($this->second)->findByEmail(self::EMAIL), 'a plain read still sees the old snapshot');
        self::assertSame(self::OTHER, $this->users($this->second)->findByEmailForUpdate(self::EMAIL)?->id()->toString());
        self::assertNull($this->users($this->second)->findByEmailForUpdate('nobody@example.com'));
    }

    public function testANewAccountIsStoredAsTheEntityManagerWouldStoreIt(): void
    {
        $users = $this->users($this->first);
        self::assertTrue($users->addIfEmailFree($this->invited(self::USER)));

        $user = $users->findById(UserId::fromString(self::USER));
        self::assertNotNull($user);
        self::assertSame([self::EMAIL, UserStatus::PENDING, UserType::CLINIC, '2026-10-27 09:30:00', null], [
            $user->email(), $user->status(), $user->type(), $user->createdAt()->format('Y-m-d H:i:s'), $user->emailVerifiedAt(),
        ]);
        self::assertSame(1, $this->number('SELECT version FROM identity_access__users WHERE id = ?', [Uuid::fromString(self::USER)->toBinary()]));

        self::assertFalse($users->addIfEmailFree($this->invited(self::OTHER)), 'the address is taken: nothing is inserted');
        self::assertNull($users->findById(UserId::fromString(self::OTHER)));
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

    private function invited(string $id): User
    {
        return User::invite(UserId::fromString($id), self::EMAIL, new \DateTimeImmutable('2026-10-27 09:30:00'), UserType::CLINIC);
    }

    private function users(Connection $connection): DoctrineUserRepository
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);

        return new DoctrineUserRepository(new EntityManager($connection, $em->getConfiguration()), new UserMapper(new DoctrineUserFactory()));
    }

    private function cleanUp(): void
    {
        $this->first->executeStatement('DELETE FROM identity_access__users WHERE email IN (?, ?)', [self::EMAIL, 'nobody@example.com']);
    }
}
