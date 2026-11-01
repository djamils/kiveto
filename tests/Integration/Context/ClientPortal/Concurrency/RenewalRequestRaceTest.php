<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\ClientPortal\Concurrency;

use App\Context\ClientPortal\Application\Command\SubmitRenewalRequest\SubmitRenewalRequest;
use App\Context\ClientPortal\Application\Command\SubmitRenewalRequest\SubmitRenewalRequestHandler;
use App\Context\ClientPortal\Domain\Request\PetCondition;
use App\Context\ClientPortal\Domain\Request\PortalRequestId;
use App\Context\ClientPortal\Domain\Request\PrescriptionRenewalRequest;
use App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository\DbalPrescriptionRenewalRequestRepository;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Tests\Unit\Context\ClientPortal\Support\PetWorld as P;
use App\Tests\Unit\Context\ClientPortal\Support\RequestWorld;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * AC 243 and S6-F8, outside the test transaction, on two real connections:
 * the unique key on the generated `open_key` keeps a single submitted
 * renewal per prescription. PHP runs one connection at a time: the second
 * insert first runs into the first transaction's uncommitted row (lock
 * wait timeout of 1 s), then, retried once the first committed, turns into
 * a no-op and reads the winner. The submits run in the Pet world's clinic
 * (ports faked), with the real repository on each connection.
 */
final class RenewalRequestRaceTest extends KernelTestCase
{
    private const string CLINIC       = '01960000-0000-7000-8000-0000000c6e01';
    private const string PRESCRIPTION = '01960000-0000-7000-8000-0000000b6e01';
    private const string FIRST        = '01960000-0000-7000-8000-0000000e6e01';
    private const string SECOND       = '01960000-0000-7000-8000-0000000e6e02';

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
            while ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        }

        $this->cleanUp();
        $this->first->close();
        $this->second->close();

        parent::tearDown();
    }

    public function testTheOpenKeyAdmitsASingleSubmittedRequest(): void
    {
        $this->first->beginTransaction();
        $this->second->beginTransaction();

        self::assertTrue(self::repository($this->first)->add(self::request(self::FIRST, self::CLINIC)));
        $this->assertWaitsForTheLock(fn () => self::repository($this->second)->add(self::request(self::SECOND, self::CLINIC)));
        $this->first->commit();

        self::assertFalse(self::repository($this->second)->add(self::request(self::SECOND, self::CLINIC)));
        self::assertSame(self::FIRST, self::repository($this->second)->findSubmittedForPrescriptionForUpdate(self::CLINIC, self::PRESCRIPTION)?->id()->toString());
        $this->second->commit();

        self::assertSame([self::FIRST], $this->committedIds());
    }

    public function testTwoConcurrentSubmitsAnswerTheSameRequest(): void
    {
        $this->first->beginTransaction();
        $this->second->beginTransaction();

        $first = $this->submit($this->first, self::FIRST);
        // The second transaction's snapshot does not see the first request yet
        $this->assertWaitsForTheLock(fn () => $this->submit($this->second, self::SECOND));
        $this->first->commit();

        $second = $this->submit($this->second, self::SECOND);
        $this->second->commit();

        self::assertSame([self::FIRST, self::FIRST], [$first, $second]);
        self::assertSame([self::FIRST], $this->committedIds());
    }

    private function submit(Connection $connection, string $id): string
    {
        $world = new RequestWorld();
        $world->prescription(self::PRESCRIPTION);

        $handler = new SubmitRenewalRequestHandler(
            $world->pets->model,
            $world->pets->documents->prescriptions,
            self::repository($connection),
            $world->pets->fakes,
            $world->pets->documents->fakes,
            $world->fakes,
            $world->fakes,
            new class($id) implements UuidGeneratorInterface {
                public function __construct(private readonly string $id)
                {
                }

                public function generate(): string
                {
                    return $this->id;
                }
            },
            $world->pets->documents->clock,
        );

        return $handler(new SubmitRenewalRequest(P::USER, P::CLINIC, self::PRESCRIPTION, 'improved'));
    }

    private function assertWaitsForTheLock(callable $write): void
    {
        try {
            $write();
            self::fail('The second writer should wait for the first transaction.');
        } catch (LockWaitTimeoutException) {
            // Only the statement is rolled back: the transaction and its snapshot remain
            self::assertTrue($this->second->isTransactionActive());
        }
    }

    /**
     * @return list<string>
     */
    private function committedIds(): array
    {
        $fresh = DriverManager::getConnection($this->first->getParams());

        try {
            return array_map(
                static fn (mixed $id): string => Uuid::fromBinary(\is_string($id) ? $id : '')->toRfc4122(),
                $fresh->fetchFirstColumn('SELECT id FROM ' . DbalPrescriptionRenewalRequestRepository::TABLE . ' WHERE prescription_id = ?', [Uuid::fromString(self::PRESCRIPTION)->toBinary()]),
            );
        } finally {
            $fresh->close();
        }
    }

    private static function repository(Connection $connection): DbalPrescriptionRenewalRequestRepository
    {
        return new DbalPrescriptionRenewalRequestRepository($connection);
    }

    private static function request(string $id, string $clinicId): PrescriptionRenewalRequest
    {
        return PrescriptionRenewalRequest::submit(
            PortalRequestId::fromString($id),
            $clinicId,
            P::CLIENT,
            P::USER,
            self::PRESCRIPTION,
            P::PET,
            PetCondition::IMPROVED,
            null,
            new \DateTimeImmutable('2026-10-29 09:00:00', new \DateTimeZone('UTC')),
        );
    }

    private function cleanUp(): void
    {
        $this->first->executeStatement(
            'DELETE FROM ' . DbalPrescriptionRenewalRequestRepository::TABLE . ' WHERE prescription_id = ?',
            [Uuid::fromString(self::PRESCRIPTION)->toBinary()],
        );
    }
}
