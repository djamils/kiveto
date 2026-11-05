<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Scheduling\Concurrency;

use App\Context\Scheduling\Application\Command\CreatePlanningBlock\CreatePlanningBlock;
use App\Context\Scheduling\Application\Command\CreatePlanningBlock\CreatePlanningBlockHandler;
use App\Context\Scheduling\Application\Command\DeletePlanningBlock\DeletePlanningBlock;
use App\Context\Scheduling\Application\Command\DeletePlanningBlock\DeletePlanningBlockHandler;
use App\Context\Scheduling\Application\Command\RescheduleAppointment\RescheduleAppointment;
use App\Context\Scheduling\Application\Command\RescheduleAppointment\RescheduleAppointmentHandler;
use App\Context\Scheduling\Application\Command\ScheduleAppointment\ScheduleAppointment;
use App\Context\Scheduling\Application\Command\ScheduleAppointment\ScheduleAppointmentHandler;
use App\Context\Scheduling\Application\Port\ClinicTimezoneResolverInterface;
use App\Context\Scheduling\Application\Port\MembershipEligibilityCheckerInterface;
use App\Context\Scheduling\Application\Service\PlanningBlockGate;
use App\Context\Scheduling\Domain\Appointment;
use App\Context\Scheduling\Domain\Exception\BlockRefusesAppointmentsException;
use App\Context\Scheduling\Domain\Exception\CannotDeletePlanningBlockWithAppointments;
use App\Context\Scheduling\Domain\Exception\SlotTakenException;
use App\Context\Scheduling\Domain\Service\LockKeys;
use App\Context\Scheduling\Domain\Service\RecurrenceExpander;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PractitionerAssignee;
use App\Context\Scheduling\Domain\ValueObject\TimeSlot;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Context\Scheduling\Infrastructure\Adapter\DbalAppointmentConflictChecker;
use App\Context\Scheduling\Infrastructure\Adapter\DbalOccupyingAppointmentsReader;
use App\Context\Scheduling\Infrastructure\Adapter\DbalPlanningBlockAppointmentCounter;
use App\Context\Scheduling\Infrastructure\Adapter\DbalPlanningBlockFinder;
use App\Context\Scheduling\Infrastructure\Adapter\DbalPlanningBlockOverlapChecker;
use App\Context\Scheduling\Infrastructure\Adapter\DbalPractitionerDayLock;
use App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Mapper\AppointmentMapper;
use App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Mapper\PlanningBlockMapper;
use App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Repository\DoctrineAppointmentRepository;
use App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Repository\DoctrinePlanningBlockRepository;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Infrastructure\Bus\Messenger\CommandBus;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\Context\Scheduling\Support\CapturingEventBus;
use App\Tests\Unit\Context\Scheduling\Support\InMemoryOnlineBookingSettings;
use App\Tests\Unit\Context\Scheduling\Support\Parties;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Uid\Uuid;

/**
 * S4d, outside the test transaction, on two real connections. Each
 * transaction first runs a plain SELECT on `scheduling__appointments`, so its
 * REPEATABLE READ snapshot predates the other's commit: only the
 * practitioner-day lock and the current reads taken under it keep two writers
 * of one practitioner's day from double booking (AC 32, 33, 34, 149).
 *
 * PHP runs one connection at a time: the second writer first runs into the
 * lock held by the first (lock wait timeout of 1 s), then retries in the
 * same transaction — with the same old snapshot — once the first committed.
 * A deadlock rolls a writer's whole transaction back: the lock reports it as
 * a contended slot and the command bus runs the command once more (S4A-05).
 */
final class PractitionerDayLockTest extends KernelTestCase
{
    private const string CLINIC = '01960000-0000-7000-8000-0000000c5d01';
    private const string VET_A  = '01960000-0000-7000-8000-0000000d5d01';
    private const string VET_B  = '01960000-0000-7000-8000-0000000d5d02';

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

    public function testOfTwoConcurrentOverlappingBookingsExactlyOneSucceeds(): void
    {
        // AC 32: both snapshots are taken before either booking exists
        $this->begin($this->second);
        $this->begin($this->first);

        $this->schedule($this->first, self::VET_A, '2026-03-11 09:00', 30);

        $this->assertWaitsForTheLock(fn () => $this->schedule($this->second, self::VET_A, '2026-03-11 09:15', 30));

        $this->first->commit();
        // The plain read still sees the old snapshot: without current reads the overlap would go unseen
        self::assertSame(0, $this->appointmentsSeenBy($this->second));

        try {
            $this->schedule($this->second, self::VET_A, '2026-03-11 09:15', 30);
            self::fail('The second booking should see the first one.');
        } catch (SlotTakenException $e) {
            self::assertStringContainsString('overlapping', $e->getMessage());
            $this->second->rollBack();
        }

        self::assertSame(1, $this->committedAppointments());
    }

    public function testASlotAcrossMidnightLocksBothLocalDates(): void
    {
        // AC 33: 00:30 local on 12 March (23:30 UTC on the 11th) locks the 12th only
        $this->begin($this->first);
        $this->schedule($this->first, self::VET_B, '2026-03-11 23:30', 20);
        $this->first->commit();
        self::assertSame([self::VET_B . ' 2026-03-12'], $this->lockRows());

        // 23:30–00:30 local on 11–12 March locks both days
        $this->begin($this->second);
        $this->begin($this->first);
        $this->schedule($this->first, self::VET_A, '2026-03-11 22:30', 60);
        self::assertSame([self::VET_A . ' 2026-03-11', self::VET_A . ' 2026-03-12', self::VET_B . ' 2026-03-12'], $this->lockRows($this->first));

        // A concurrent 00:00–00:20 booking on 12 March waits for the 12th, then is refused
        $this->assertWaitsForTheLock(fn () => $this->schedule($this->second, self::VET_A, '2026-03-11 23:00', 20));
        $this->first->commit();

        $this->expectException(SlotTakenException::class);
        $this->schedule($this->second, self::VET_A, '2026-03-11 23:00', 20);
    }

    public function testCrossedReschedulesBothSucceedOneAfterTheOther(): void
    {
        // AC 34: X (A, 11 March 09:00) moves to B on 12 March; Y (B, 12 March 13:00) moves to A on 11 March
        $this->begin($this->first);
        $x = $this->schedule($this->first, self::VET_A, '2026-03-11 08:00', 30);
        $y = $this->schedule($this->first, self::VET_B, '2026-03-12 12:00', 30);
        $this->first->commit();

        $this->begin($this->second);
        $this->begin($this->first);

        $this->reschedule($this->first, $x, self::VET_B, '2026-03-12 09:00');
        // Both lock {(A, 11), (B, 12)} in the same order: the second waits, it never deadlocks
        $this->assertWaitsForTheLock(fn () => $this->reschedule($this->second, $y, self::VET_A, '2026-03-11 10:00'));
        $this->first->commit();

        $this->reschedule($this->second, $y, self::VET_A, '2026-03-11 10:00');
        $this->second->commit();

        self::assertSame([
            self::VET_A . ' 2026-03-11 10:00:00',
            self::VET_B . ' 2026-03-12 09:00:00',
        ], $this->committedSlots());
    }

    public function testADeadlockWhileLockingRollsBackAndIsAContendedSlot(): void
    {
        // S4A-05: another writer holds (A, 12 March) and many more rows, then asks for (A, 11 March)
        // while this booking holds it and waits for the 12th: MySQL rolls this lighter transaction
        // back. The lock reopens nothing: the caller learns at once, its transaction is gone.
        $other = $this->otherWriter(
            holds: [[self::VET_B, '2026-04-01'], [self::VET_B, '2026-04-02'], [self::VET_B, '2026-04-03'], [self::VET_B, '2026-04-04'], [self::VET_B, '2026-04-05'], [self::VET_A, '2026-03-12']],
            thenTakes: [self::VET_A, '2026-03-11'],
        );

        $this->begin($this->first);

        try {
            $this->schedule($this->first, self::VET_A, '2026-03-11 22:30', 60);
            self::fail('The deadlock should reach the caller.');
        } catch (SlotTakenException $e) {
            self::assertSame(SlotTakenException::CONTENDED, $e->reason);
            self::assertInstanceOf(DeadlockException::class, $e->getPrevious());
        }

        // No raw START TRANSACTION: DBAL still counts the one transaction its caller began
        self::assertSame(1, $this->first->getTransactionNestingLevel());
        $this->first->rollBack();

        self::assertSame("ready\ndone\n", $this->finish($other));
        self::assertSame([], $this->committedSlots());
    }

    public function testTheCommandBusRunsADeadlockedBookingOnceMore(): void
    {
        // S4A-05: the same deadlock through the bus, which owns the transaction: the whole
        // command runs again once the other writer committed, and the booking is made
        $other = $this->otherWriter(
            holds: [[self::VET_B, '2026-04-01'], [self::VET_B, '2026-04-02'], [self::VET_B, '2026-04-03'], [self::VET_B, '2026-04-04'], [self::VET_B, '2026-04-05'], [self::VET_A, '2026-03-12']],
            thenTakes: [self::VET_A, '2026-03-11'],
        );

        $bus = $this->transactionalBus($this->first, fn (): string => $this->schedule($this->first, self::VET_A, '2026-03-11 22:30', 60));
        $id  = new CommandBus($bus, $this->registry($this->first))->dispatch(new \stdClass());

        self::assertIsString($id);
        self::assertCount(2, $bus);
        self::assertSame("ready\ndone\n", $this->finish($other));
        self::assertSame([self::VET_A . ' 2026-03-11 22:30:00'], $this->committedSlots());
    }

    public function testBookingsOfOnePractitionerOnDifferentDaysDoNotLockEachOther(): void
    {
        // S4A-02: Dr A's only past booking, cancelled, is at 09:15 on 11 March. A booking on the
        // 11th and one on the 20th check their slot as current reads in turn; the scan of the 20th
        // starts a day before its slot, so it no longer locks the history up to the 11th and the
        // booking of the 11th is written without waiting for it (it used to: then a deadlock).
        $this->begin($this->first);
        $cancelled = $this->schedule($this->first, self::VET_A, '2026-03-11 09:15', 30);
        $this->first->executeStatement('UPDATE scheduling__appointments SET status = ? WHERE id = ?', [AppointmentStatus::CANCELLED->value, Uuid::fromString($cancelled)->toBinary()]);
        $this->first->commit();
        $this->first->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');

        $this->begin($this->first);
        $this->begin($this->second);
        self::assertFalse($this->checkLocked($this->first, '2026-03-11 09:00'));
        self::assertFalse($this->checkLocked($this->second, '2026-03-20 09:00'));

        // Would time out while the scan of the 20th covered the 11th
        $this->insert($this->first, '2026-03-11 09:00');
        $this->first->commit();

        $this->insert($this->second, '2026-03-20 09:00');
        $this->second->commit();

        self::assertSame([
            self::VET_A . ' 2026-03-11 09:00:00',
            self::VET_A . ' 2026-03-11 09:15:00',
            self::VET_A . ' 2026-03-20 09:00:00',
        ], $this->committedSlots());
    }

    public function testABlockCannotBeDeletedUnderABookingCommittedMeanwhile(): void
    {
        // AC 149: the booking takes the day first; the deletion waits, then counts it
        $this->begin($this->first);
        $block = $this->createBlock($this->first, 'consultation', '2026-03-11');
        $this->first->commit();

        $this->begin($this->second);
        $this->begin($this->first);
        $this->schedule($this->first, self::VET_A, '2026-03-11 08:00', 30);

        $this->assertWaitsForTheLock(fn () => $this->deleteBlock($this->second, $block));
        $this->first->commit();

        $this->expectException(CannotDeletePlanningBlockWithAppointments::class);
        $this->deleteBlock($this->second, $block);
    }

    public function testABookingInFlightSeesTheBlockCreatedMeanwhile(): void
    {
        // AC 149: a leave block committed while the booking waited refuses it
        $this->begin($this->second);
        $this->begin($this->first);
        $this->createBlock($this->first, 'leave', '2026-03-11');

        $this->assertWaitsForTheLock(fn () => $this->schedule($this->second, self::VET_A, '2026-03-11 08:00', 30));
        $this->first->commit();

        try {
            $this->schedule($this->second, self::VET_A, '2026-03-11 08:00', 30);
            self::fail('The booking should see the leave block.');
        } catch (BlockRefusesAppointmentsException) {
            $this->second->rollBack();
        }

        self::assertSame(0, $this->committedAppointments());
    }

    private function schedule(Connection $connection, string $practitioner, string $startsAtUtc, int $duration): string
    {
        $em = $this->entityManager($connection);

        $handler = new ScheduleAppointmentHandler(
            appointmentRepository: new DoctrineAppointmentRepository($em, new AppointmentMapper()),
            membershipEligibilityChecker: $this->eligible(),
            conflictChecker: new DbalAppointmentConflictChecker($connection),
            uuidGenerator: $this->uuids(),
            clock: new FrozenClock(self::utc('2026-03-01 08:00')),
            planningBlockGate: $this->gate($connection),
            timezoneResolver: $this->paris(),
            domainEventPublisher: new DomainEventPublisher(new CapturingEventBus()),
            dayLock: new DbalPractitionerDayLock($connection),
            parties: Parties::ofTheClinic(),
        );

        return $handler(new ScheduleAppointment(
            clinicId: self::CLINIC,
            practitionerUserId: $practitioner,
            startsAtUtc: self::utc($startsAtUtc),
            durationMinutes: $duration,
        ));
    }

    /**
     * The practitioner-day lock, then the overlap and capacity scans of the
     * slot as current reads — what a booking does before it writes.
     */
    private function checkLocked(Connection $connection, string $startsAtUtc): bool
    {
        $slot   = new TimeSlot(self::utc($startsAtUtc), 30);
        $clinic = ClinicId::fromString(self::CLINIC);
        $vet    = UserId::fromString(self::VET_A);

        new DbalPractitionerDayLock($connection)->lock($clinic, LockKeys::forSlot($vet, $slot->startsAtUtc(), $slot->endsAtUtc(), new \DateTimeZone('Europe/Paris')));
        new DbalPlanningBlockAppointmentCounter($connection)->countOccupyingInWindowLocked($clinic, $vet, $slot->startsAtUtc()->modify('-1 hour'), $slot->endsAtUtc()->modify('+1 hour'));

        return new DbalAppointmentConflictChecker($connection)->hasOverlapLocked($clinic, $vet, $slot);
    }

    private function insert(Connection $connection, string $startsAtUtc): void
    {
        new DoctrineAppointmentRepository($this->entityManager($connection), new AppointmentMapper())->save(Appointment::schedule(
            id: AppointmentId::fromString($this->uuids()->generate()),
            clinicId: ClinicId::fromString(self::CLINIC),
            practitionerAssignee: new PractitionerAssignee(UserId::fromString(self::VET_A)),
            timeSlot: new TimeSlot(self::utc($startsAtUtc), 30),
            reason: null,
            notes: null,
            createdAt: self::utc('2026-03-01 08:00'),
        ));
    }

    /**
     * A command bus stack reduced to its transaction: begin, run, commit —
     * or roll back and report the failure as Messenger does.
     *
     * @param \Closure(): mixed $write
     */
    private function transactionalBus(Connection $connection, \Closure $write): MessageBusInterface&\Countable
    {
        return new class($connection, $write) implements MessageBusInterface, \Countable {
            public int $runs = 0;

            public function __construct(private readonly Connection $connection, private readonly \Closure $write)
            {
            }

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                ++$this->runs;
                $this->connection->beginTransaction();

                try {
                    $result = ($this->write)();
                    $this->connection->commit();
                } catch (\Throwable $e) {
                    if ($this->connection->isTransactionActive()) {
                        $this->connection->rollBack();
                    }

                    throw new HandlerFailedException(Envelope::wrap($message, $stamps), [$e]);
                }

                return Envelope::wrap($message, $stamps)->with(new HandledStamp($result, 'handler'));
            }

            /**
             * @return int<0, max> how many times the command ran
             */
            public function count(): int
            {
                return max(0, $this->runs);
            }
        };
    }

    private function registry(Connection $connection): ManagerRegistry
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getConnection')->willReturn($connection);
        $registry->method('getManager')->willReturn($this->entityManager($connection));

        return $registry;
    }

    private function reschedule(Connection $connection, string $appointmentId, string $practitioner, string $startsAtUtc): void
    {
        $em = $this->entityManager($connection);

        new RescheduleAppointmentHandler(
            appointmentRepository: new DoctrineAppointmentRepository($em, new AppointmentMapper()),
            conflictChecker: new DbalAppointmentConflictChecker($connection),
            membershipEligibilityChecker: $this->eligible(),
            clock: new FrozenClock(self::utc('2026-03-01 08:00')),
            domainEventPublisher: new DomainEventPublisher(new CapturingEventBus()),
            onlineBookingSettings: new InMemoryOnlineBookingSettings(),
            timezoneResolver: $this->paris(),
            dayLock: new DbalPractitionerDayLock($connection),
            planningBlockGate: $this->gate($connection),
            parties: Parties::ofTheClinic(),
        )(new RescheduleAppointment(
            clinicId: self::CLINIC,
            appointmentId: $appointmentId,
            startsAtUtc: self::utc($startsAtUtc),
            durationMinutes: 30,
            practitionerUserId: $practitioner,
        ));
    }

    private function createBlock(Connection $connection, string $type, string $date): string
    {
        return new CreatePlanningBlockHandler(
            repository: new DoctrinePlanningBlockRepository($this->entityManager($connection), new PlanningBlockMapper()),
            overlapChecker: new DbalPlanningBlockOverlapChecker($connection),
            uuidGenerator: $this->uuids(),
            timezoneResolver: $this->paris(),
            dayLock: new DbalPractitionerDayLock($connection),
            clock: new FrozenClock(self::utc('2026-03-01 08:00')),
        )(new CreatePlanningBlock(
            clinicId: self::CLINIC,
            staffUserId: self::VET_A,
            type: $type,
            date: $date,
            startTime: '08:00',
            endTime: '12:00',
            capacityPerHour: 'leave' === $type ? 0 : 4,
            recurrenceFreq: 'NONE',
            recurrenceUntil: null,
            note: null,
        ));
    }

    private function deleteBlock(Connection $connection, string $blockId): void
    {
        new DeletePlanningBlockHandler(
            repository: new DoctrinePlanningBlockRepository($this->entityManager($connection), new PlanningBlockMapper()),
            appointmentsReader: new DbalOccupyingAppointmentsReader($connection),
            timezoneResolver: $this->paris(),
            dayLock: new DbalPractitionerDayLock($connection),
            clock: new FrozenClock(self::utc('2026-03-01 08:00')),
        )(new DeletePlanningBlock(blockId: $blockId, clinicId: self::CLINIC));
    }

    private function gate(Connection $connection): PlanningBlockGate
    {
        return new PlanningBlockGate(new DbalPlanningBlockFinder(
            $connection,
            new RecurrenceExpander(),
            new DbalPlanningBlockAppointmentCounter($connection),
            $this->paris(),
        ));
    }

    private function assertWaitsForTheLock(callable $write): void
    {
        try {
            $write();
            self::fail('The second writer should wait for the practitioner-day lock.');
        } catch (LockWaitTimeoutException) {
            // Only the statement is rolled back: the transaction and its snapshot remain
            self::assertTrue($this->second->isTransactionActive());
        }
    }

    /**
     * Starts a transaction and fixes its REPEATABLE READ snapshot with a plain read.
     */
    private function begin(Connection $connection): void
    {
        $connection->beginTransaction();
        $this->appointmentsSeenBy($connection);
    }

    private function appointmentsSeenBy(Connection $connection): int
    {
        $count = $connection->fetchOne('SELECT COUNT(*) FROM scheduling__appointments WHERE clinic_id = ?', [Uuid::fromString(self::CLINIC)->toBinary()]);
        \assert(is_numeric($count));

        return (int) $count;
    }

    private function committedAppointments(): int
    {
        $fresh = $this->freshConnection();

        try {
            return $this->appointmentsSeenBy($fresh);
        } finally {
            $fresh->close();
        }
    }

    /**
     * @return list<string>
     */
    private function committedSlots(): array
    {
        $fresh = $this->freshConnection();

        try {
            $rows = $fresh->fetchAllAssociative('SELECT practitioner_user_id, starts_at_utc FROM scheduling__appointments WHERE clinic_id = ? ORDER BY practitioner_user_id, starts_at_utc', [Uuid::fromString(self::CLINIC)->toBinary()]);
        } finally {
            $fresh->close();
        }

        return array_map(static function (array $row): string {
            \assert(\is_string($row['practitioner_user_id']) && \is_string($row['starts_at_utc']));

            return Uuid::fromBinary($row['practitioner_user_id'])->toRfc4122() . ' ' . $row['starts_at_utc'];
        }, $rows);
    }

    /**
     * @return list<string>
     */
    private function lockRows(?Connection $connection = null): array
    {
        $rows = ($connection ?? $this->first)->fetchAllAssociative('SELECT practitioner_user_id, local_date FROM scheduling__practitioner_day_locks WHERE clinic_id = ? ORDER BY practitioner_user_id, local_date', [Uuid::fromString(self::CLINIC)->toBinary()]);

        return array_map(static function (array $row): string {
            \assert(\is_string($row['practitioner_user_id']) && \is_string($row['local_date']));

            return Uuid::fromBinary($row['practitioner_user_id'])->toRfc4122() . ' ' . $row['local_date'];
        }, $rows);
    }

    /**
     * A writer in another process: it takes $holds, says "ready", sleeps one
     * second, then takes $thenTakes and commits ("done").
     *
     * @param list<array{string, string}> $holds
     * @param array{string, string}       $thenTakes
     *
     * @return array{process: resource, stdout: resource}
     */
    private function otherWriter(array $holds, array $thenTakes): array
    {
        $params = $this->first->getParams();
        $env    = json_encode([
            'dsn'      => \sprintf('mysql:host=%s;port=%d;dbname=%s', $params['host'] ?? '127.0.0.1', $params['port'] ?? 3306, $params['dbname'] ?? ''),
            'user'     => $params['user'] ?? '',
            'password' => $params['password'] ?? '',
            'clinic'   => bin2hex(Uuid::fromString(self::CLINIC)->toBinary()),
            'holds'    => array_map(static fn (array $key): array => [bin2hex(Uuid::fromString($key[0])->toBinary()), $key[1]], $holds),
            'then'     => [bin2hex(Uuid::fromString($thenTakes[0])->toBinary()), $thenTakes[1]],
        ], \JSON_THROW_ON_ERROR);

        $script = <<<'PHP'
            $c = json_decode((string) getenv('DAY_LOCK_WRITER'), true, 512, JSON_THROW_ON_ERROR);
            $pdo = new PDO($c['dsn'], $c['user'], $c['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec('SET SESSION innodb_lock_wait_timeout = 10');
            $insert = $pdo->prepare('INSERT INTO scheduling__practitioner_day_locks (clinic_id, practitioner_user_id, local_date) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE local_date = local_date');
            $pdo->beginTransaction();
            foreach ($c['holds'] as [$practitioner, $day]) {
                $insert->execute([hex2bin($c['clinic']), hex2bin($practitioner), $day]);
            }
            echo "ready\n";
            fflush(STDOUT);
            $pdo->exec('DO SLEEP(1)');
            $insert->execute([hex2bin($c['clinic']), hex2bin($c['then'][0]), $c['then'][1]]);
            $pdo->commit();
            echo "done\n";
            PHP;

        $process = proc_open([\PHP_BINARY, '-r', $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, ['DAY_LOCK_WRITER' => $env] + getenv());
        self::assertIsResource($process);
        \assert(isset($pipes[1]));

        // Wait until the other writer holds its rows
        self::assertSame("ready\n", fgets($pipes[1]));

        return ['process' => $process, 'stdout' => $pipes[1]];
    }

    /**
     * @param array{process: resource, stdout: resource} $writer
     */
    private function finish(array $writer): string
    {
        $output = "ready\n" . stream_get_contents($writer['stdout']);
        proc_close($writer['process']);

        return $output;
    }

    private function entityManager(Connection $connection): EntityManager
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);

        return new EntityManager($connection, $em->getConfiguration());
    }

    private function uuids(): UuidGeneratorInterface
    {
        $uuids = self::getContainer()->get(UuidGeneratorInterface::class);
        \assert($uuids instanceof UuidGeneratorInterface);

        return $uuids;
    }

    private function eligible(): MembershipEligibilityCheckerInterface
    {
        return new class implements MembershipEligibilityCheckerInterface {
            public function isUserEligibleForClinicAt(UserId $userId, ClinicId $clinicId, \DateTimeImmutable $at, array $allowedRoles): bool
            {
                return true;
            }

            public function listEligiblePractitionerUsersForClinic(ClinicId $clinicId, \DateTimeImmutable $at, array $allowedRoles): array
            {
                return [];
            }
        };
    }

    private function paris(): ClinicTimezoneResolverInterface
    {
        return new class implements ClinicTimezoneResolverInterface {
            public function resolveTimezone(ClinicId $clinicId): \DateTimeZone
            {
                return new \DateTimeZone('Europe/Paris');
            }
        };
    }

    private function freshConnection(): Connection
    {
        return DriverManager::getConnection($this->first->getParams());
    }

    private function cleanUp(): void
    {
        $clinic = Uuid::fromString(self::CLINIC)->toBinary();
        foreach (['scheduling__appointments', 'scheduling__planning_blocks', 'scheduling__practitioner_day_locks'] as $table) {
            $this->first->executeStatement(\sprintf('DELETE FROM %s WHERE clinic_id = ?', $table), [$clinic]);
        }
    }

    private static function utc(string $at): \DateTimeImmutable
    {
        return new \DateTimeImmutable($at, new \DateTimeZone('UTC'));
    }
}
