<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Scheduling\Concurrency;

use App\Context\Scheduling\Application\Command\BookOnlineAppointment\BookOnlineAppointment;
use App\Context\Scheduling\Application\Command\BookOnlineAppointment\BookOnlineAppointmentHandler;
use App\Context\Scheduling\Application\Port\ClinicOpeningHours;
use App\Context\Scheduling\Application\Port\PlanningBlockOccurrence;
use App\Context\Scheduling\Application\Service\OnlineSlotChecker;
use App\Context\Scheduling\Domain\Booking\BookingMotif;
use App\Context\Scheduling\Domain\Booking\BookingMotifId;
use App\Context\Scheduling\Domain\Booking\ConfirmationMode;
use App\Context\Scheduling\Domain\Booking\OnlineBookingSettings;
use App\Context\Scheduling\Domain\Exception\SlotTakenException;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockType;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Context\Scheduling\Infrastructure\Adapter\DbalOccupyingAppointmentsReader;
use App\Context\Scheduling\Infrastructure\Adapter\DbalPractitionerDayLock;
use App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Mapper\AppointmentMapper;
use App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Repository\DoctrineAppointmentRepository;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Infrastructure\Identifier\SymfonyUuidV7Generator;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\Context\Scheduling\Support\CapturingEventBus;
use App\Tests\Unit\Context\Scheduling\Support\FakeAvailabilitySources;
use App\Tests\Unit\Context\Scheduling\Support\InMemoryBookingMotifs;
use App\Tests\Unit\Context\Scheduling\Support\InMemoryOnlineBookingSettings;
use App\Tests\Unit\Context\Scheduling\Support\ParisTimezone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * AC 45 and AC 151, outside the test transaction, on two real connections:
 * two owners book the same online slot; exactly one gets it, the other
 * `slot_taken`. A retry of the winner's request (same owner, animal, motif
 * and start) answers its appointment instead. Each transaction first fixes
 * its REPEATABLE READ snapshot with a plain read: only the practitioner-day
 * lock and the current reads taken under it let the loser see the winner's
 * commit.
 *
 * PHP runs one connection at a time: the second booking first runs into the
 * lock held by the first (lock wait timeout of 1 s), then retries in the
 * same transaction once the first committed.
 */
final class OnlineBookingRaceTest extends KernelTestCase
{
    private const string CLINIC = '01960000-0000-7000-8000-0000000c5e02';
    private const string VET    = '01960000-0000-7000-8000-0000000d5e02';
    private const string MOTIF  = '01960000-0000-7000-8000-0000000b5e02';
    private const string SOPHIE = '01960000-0000-7000-8000-0000000e5e01';
    private const string MARC   = '01960000-0000-7000-8000-0000000e5e02';
    private const string MILO   = '01960000-0000-7000-8000-0000000f5e01';
    private const string NALA   = '01960000-0000-7000-8000-0000000f5e02';
    private const string SLOT   = '2026-04-14T10:00:00+02:00';

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

    public function testTwoOwnersBookingTheSameSlotGetOneAppointment(): void
    {
        $this->begin($this->second);
        $this->begin($this->first);

        $sophie = $this->book($this->first, self::SOPHIE, self::MILO);

        $this->assertWaitsForTheLock(fn () => $this->book($this->second, self::MARC, self::NALA));
        $this->first->commit();

        try {
            $this->book($this->second, self::MARC, self::NALA);
            self::fail('The slot should be taken.');
        } catch (SlotTakenException $e) {
            // 409 `slot_taken`
            self::assertSame(SlotTakenException::UNAVAILABLE, $e->reason);
            $this->second->rollBack();
        }

        self::assertSame([$sophie], $this->committedAppointments());
    }

    public function testARetryOfTheWinningRequestAnswersItsAppointment(): void
    {
        $this->begin($this->second);
        $this->begin($this->first);

        $sophie = $this->book($this->first, self::SOPHIE, self::MILO);

        $this->assertWaitsForTheLock(fn () => $this->book($this->second, self::SOPHIE, self::MILO));
        $this->first->commit();

        self::assertSame($sophie, $this->book($this->second, self::SOPHIE, self::MILO));
        $this->second->commit();

        self::assertSame([$sophie], $this->committedAppointments());
    }

    private function book(Connection $connection, string $ownerId, string $animalId): string
    {
        $sources           = new FakeAvailabilitySources();
        $weekday           = [['opensAt' => '08:30', 'closesAt' => '12:30']];
        $sources->clinic   = new ClinicOpeningHours(true, new \DateTimeZone('Europe/Paris'), [1 => $weekday, 2 => $weekday], []);
        $sources->eligible = [self::VET];
        // Capacity 0: unlimited, only the overlap decides
        $sources->occurrences = [new PlanningBlockOccurrence('2026-04-14', '09:00', '12:00', PlanningBlockType::CONSULTATION, 0)];

        $motifs = new InMemoryBookingMotifs();
        $motifs->save(BookingMotif::create(
            BookingMotifId::fromString(self::MOTIF),
            ClinicId::fromString(self::CLINIC),
            'Consultation',
            null,
            30,
            true,
            ConfirmationMode::AUTO,
            [PlanningBlockType::CONSULTATION],
            [UserId::fromString(self::VET)],
            null,
            true,
            0,
        ));

        $settings = new InMemoryOnlineBookingSettings();
        $settings->save(new OnlineBookingSettings(ClinicId::fromString(self::CLINIC), true, 15, 120, 24, 60, 24, false));

        // Monday 13 April, 08:00 in Paris
        $clock      = new FrozenClock(new \DateTimeImmutable('2026-04-13 06:00', new \DateTimeZone('UTC')));
        $repository = $this->repository($connection);

        return new BookOnlineAppointmentHandler(
            appointments: $repository,
            motifs: $motifs,
            settings: $settings,
            slots: new OnlineSlotChecker($motifs, $settings, $sources, $sources, $sources, new DbalOccupyingAppointmentsReader($connection), $clock),
            timezoneResolver: new ParisTimezone(),
            dayLock: new DbalPractitionerDayLock($connection),
            uuidGenerator: new SymfonyUuidV7Generator(),
            clock: $clock,
            domainEventPublisher: new DomainEventPublisher(new CapturingEventBus()),
        )(new BookOnlineAppointment(self::CLINIC, self::MOTIF, self::VET, new \DateTimeImmutable(self::SLOT), $ownerId, $animalId));
    }

    private function assertWaitsForTheLock(callable $write): void
    {
        try {
            $write();
            self::fail('The second booking should wait for the practitioner-day lock.');
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
        self::assertFalse($connection->fetchOne('SELECT 1 FROM scheduling__appointments WHERE clinic_id = ? LIMIT 1', [Uuid::fromString(self::CLINIC)->toBinary()]));
    }

    /**
     * @return list<string>
     */
    private function committedAppointments(): array
    {
        $fresh = DriverManager::getConnection($this->first->getParams());

        try {
            $ids = $fresh->fetchFirstColumn('SELECT id FROM scheduling__appointments WHERE clinic_id = ?', [Uuid::fromString(self::CLINIC)->toBinary()]);

            return array_map(static fn (mixed $id): string => Uuid::fromBinary(\is_string($id) ? $id : '')->toRfc4122(), $ids);
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
}
