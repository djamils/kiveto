<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Scheduling\Concurrency;

use App\Context\Scheduling\Application\Command\CancelAppointment\CancelAppointment;
use App\Context\Scheduling\Application\Command\CancelAppointment\CancelAppointmentHandler;
use App\Context\Scheduling\Application\Command\ConfirmAppointment\ConfirmAppointment;
use App\Context\Scheduling\Application\Command\ConfirmAppointment\ConfirmAppointmentHandler;
use App\Context\Scheduling\Application\Service\PendingAppointmentLoader;
use App\Context\Scheduling\Domain\Appointment;
use App\Context\Scheduling\Domain\Exception\AppointmentAlreadyHandledException;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\AppointmentSource;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PractitionerAssignee;
use App\Context\Scheduling\Domain\ValueObject\TimeSlot;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Context\Scheduling\Infrastructure\Adapter\DbalPractitionerDayLock;
use App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Mapper\AppointmentMapper;
use App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Repository\DoctrineAppointmentRepository;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\Context\Scheduling\Support\CapturingEventBus;
use App\Tests\Unit\Context\Scheduling\Support\ParisTimezone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Outside the test transaction, on two real connections: a confirm and a
 * clinic cancel (a decline, « Annuler » on the agenda) of one pending
 * request race; exactly one wins. Each
 * transaction first fixes its REPEATABLE READ snapshot with a plain read of
 * the request (still pending): only the practitioner-day lock and the
 * `FOR UPDATE` read taken under it let the loser see the winner's commit.
 *
 * PHP runs one connection at a time: the loser first runs into the lock
 * held by the winner (lock wait timeout of 1 s), then retries in the same
 * transaction once the winner committed.
 */
final class ClinicCancelRaceTest extends KernelTestCase
{
    private const string CLINIC      = '01960000-0000-7000-8000-0000000c5e02';
    private const string VET         = '01960000-0000-7000-8000-0000000d5e02';
    private const string APPOINTMENT = '01960000-0000-7000-8000-0000000a5e02';

    /** Before the expiry, when one assistant clicks « Confirmer » and another « Annuler ». */
    private const string NOW = '2026-03-10 07:59';

    private Connection $first;
    private Connection $second;

    /** @var array<int, EntityManager> one per connection, kept across retries like a request's */
    private array $managers = [];

    protected function setUp(): void
    {
        $default = self::getContainer()->get('doctrine.dbal.default_connection');
        \assert($default instanceof Connection);

        $this->first  = DriverManager::getConnection($default->getParams());
        $this->second = DriverManager::getConnection($default->getParams());
        $this->second->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');

        $this->cleanUp();

        // Requested on 9 March for 11 March 10:00 Paris, expiring on 10 March at 08:00 UTC
        $this->first->beginTransaction();
        $this->repository($this->first)->save(Appointment::request(
            id: AppointmentId::fromString(self::APPOINTMENT),
            clinicId: ClinicId::fromString(self::CLINIC),
            practitionerAssignee: new PractitionerAssignee(UserId::fromString(self::VET)),
            timeSlot: new TimeSlot(self::utc('2026-03-11 09:00'), 30),
            reason: 'Vaccination',
            notes: null,
            createdAt: self::utc('2026-03-08 08:00'),
            // A known client's request (a new client's is confirmed through « Demandes Portal »)
            ownerId: '01960000-0000-7000-8000-0000000c0d02',
            animalId: null,
            bookingMotifId: null,
            source: AppointmentSource::PORTAL,
            pendingExpiresAt: self::utc('2026-03-10 08:00'),
        ));
        $this->first->commit();
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

    public function testAConfirmCommittedFirstRefusesTheClinicCancel(): void
    {
        $this->begin($this->second);
        $this->begin($this->first);

        $confirmEvents = $this->confirm($this->first);

        $cancelEvents = new CapturingEventBus();
        $this->assertWaitsForTheLock(fn () => $this->cancel($this->second, $cancelEvents));
        $this->first->commit();

        // Still pending in the second snapshot, planned once read for update
        self::assertSame('PENDING_CLINIC_CONFIRMATION', $this->statusSeenBy($this->second));

        try {
            $this->cancel($this->second, $cancelEvents);
            self::fail('The clinic cancel should see the confirmation.');
        } catch (AppointmentAlreadyHandledException $e) {
            self::assertSame('Ce RDV a déjà été traité.', $e->getMessage());
            $this->second->rollBack();
        }

        self::assertCount(1, $confirmEvents->events);
        self::assertSame([], $cancelEvents->events);
        self::assertSame('PLANNED', $this->committedStatus());
    }

    public function testAClinicCancelCommittedFirstRefusesTheConfirm(): void
    {
        $this->begin($this->second);
        $this->begin($this->first);

        $cancelEvents = new CapturingEventBus();
        $this->cancel($this->first, $cancelEvents);

        $this->assertWaitsForTheLock(fn () => $this->confirm($this->second));
        $this->first->commit();

        try {
            $this->confirm($this->second);
            self::fail('The confirm should see the decline.');
        } catch (AppointmentAlreadyHandledException $e) {
            self::assertSame('Ce RDV a déjà été traité.', $e->getMessage());
            $this->second->rollBack();
        }

        self::assertCount(1, $cancelEvents->events);
        self::assertSame('DECLINED', $this->committedStatus());
    }

    private function confirm(Connection $connection): CapturingEventBus
    {
        $events     = new CapturingEventBus();
        $repository = $this->repository($connection);

        new ConfirmAppointmentHandler(
            loader: new PendingAppointmentLoader($repository, new ParisTimezone(), new DbalPractitionerDayLock($connection)),
            appointments: $repository,
            clock: new FrozenClock(self::utc(self::NOW)),
            domainEventPublisher: new DomainEventPublisher($events),
        )(new ConfirmAppointment(self::APPOINTMENT, self::CLINIC));

        return $events;
    }

    private function cancel(Connection $connection, CapturingEventBus $events): void
    {
        $repository = $this->repository($connection);

        new CancelAppointmentHandler(
            appointmentRepository: $repository,
            loader: new PendingAppointmentLoader($repository, new ParisTimezone(), new DbalPractitionerDayLock($connection)),
            clock: new FrozenClock(self::utc(self::NOW)),
            domainEventPublisher: new DomainEventPublisher($events),
        )(new CancelAppointment(self::APPOINTMENT, self::CLINIC));
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
        self::assertSame('PENDING_CLINIC_CONFIRMATION', $this->statusSeenBy($connection));
    }

    private function statusSeenBy(Connection $connection): string
    {
        $status = $connection->fetchOne('SELECT status FROM scheduling__appointments WHERE id = ?', [Uuid::fromString(self::APPOINTMENT)->toBinary()]);
        \assert(\is_string($status));

        return $status;
    }

    private function committedStatus(): string
    {
        $fresh = DriverManager::getConnection($this->first->getParams());

        try {
            return $this->statusSeenBy($fresh);
        } finally {
            $fresh->close();
        }
    }

    private function repository(Connection $connection): DoctrineAppointmentRepository
    {
        $key = spl_object_id($connection);

        if (!isset($this->managers[$key])) {
            $em = self::getContainer()->get(EntityManagerInterface::class);
            \assert($em instanceof EntityManagerInterface);
            $this->managers[$key] = new EntityManager($connection, $em->getConfiguration());
        }

        return new DoctrineAppointmentRepository($this->managers[$key], new AppointmentMapper());
    }

    private function cleanUp(): void
    {
        $clinic = Uuid::fromString(self::CLINIC)->toBinary();
        foreach (['scheduling__appointments', 'scheduling__practitioner_day_locks'] as $table) {
            $this->first->executeStatement(\sprintf('DELETE FROM %s WHERE clinic_id = ?', $table), [$clinic]);
        }
    }

    private static function utc(string $at): \DateTimeImmutable
    {
        return new \DateTimeImmutable($at, new \DateTimeZone('UTC'));
    }
}
