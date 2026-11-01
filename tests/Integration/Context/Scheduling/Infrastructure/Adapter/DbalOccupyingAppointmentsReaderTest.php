<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Scheduling\Infrastructure\Adapter;

use App\Context\Scheduling\Application\Port\OccupyingAppointmentsReaderInterface;
use App\Context\Scheduling\Domain\Booking\BusyInterval;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\Context\Scheduling\Factory\AppointmentEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class DbalOccupyingAppointmentsReaderTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC_ID = '12345678-9abc-def0-1234-56789abcd2f1';
    private const string VET       = '01234567-89ab-cdef-0123-456789abc2f1';
    private const string OTHER_VET = '01234567-89ab-cdef-0123-456789abc2f2';
    private const string MOVED     = '01960000-0000-7000-8000-0000000a2003';

    private OccupyingAppointmentsReaderInterface $reader;

    protected function setUp(): void
    {
        parent::setUp();

        $reader = self::getContainer()->get(OccupyingAppointmentsReaderInterface::class);
        \assert($reader instanceof OccupyingAppointmentsReaderInterface);
        $this->reader = $reader;

        ClinicEntityFactory::new()->withId(self::CLINIC_ID)->create(['timeZone' => 'Europe/Paris']);

        // Started the evening before, ends inside the window
        $this->appointment('01960000-0000-7000-8000-0000000a2001', '2026-04-12 21:30', 60, AppointmentStatus::PLANNED);
        $this->appointment('01960000-0000-7000-8000-0000000a2002', '2026-04-13 08:00', 30, AppointmentStatus::PENDING_CLINIC_CONFIRMATION);
        $this->appointment(self::MOVED, '2026-04-13 09:00', 30, AppointmentStatus::PLANNED);
        // Freed slots, another practitioner, outside the window
        $this->appointment('01960000-0000-7000-8000-0000000a2004', '2026-04-13 10:00', 30, AppointmentStatus::CANCELLED);
        $this->appointment('01960000-0000-7000-8000-0000000a2005', '2026-04-13 10:30', 30, AppointmentStatus::DECLINED);
        $this->appointment('01960000-0000-7000-8000-0000000a2006', '2026-04-13 11:00', 30, AppointmentStatus::PLANNED, self::OTHER_VET);
        $this->appointment('01960000-0000-7000-8000-0000000a2007', '2026-04-13 22:00', 30, AppointmentStatus::PLANNED);
        $this->appointment('01960000-0000-7000-8000-0000000a2008', '2026-04-12 21:00', 30, AppointmentStatus::PLANNED);
    }

    public function testReturnsThePractitionersOccupyingAppointmentsOverlappingTheWindow(): void
    {
        // Monday 13 April in Paris: 12 April 22:00 UTC → 13 April 22:00 UTC
        $busy = $this->reader->occupyingBetween(
            ClinicId::fromString(self::CLINIC_ID),
            UserId::fromString(self::VET),
            new \DateTimeImmutable('2026-04-13T00:00:00+02:00'),
            new \DateTimeImmutable('2026-04-14T00:00:00+02:00'),
        );

        self::assertSame(
            [
                ['2026-04-12T21:30:00+00:00', '2026-04-12T22:30:00+00:00', true],
                ['2026-04-13T08:00:00+00:00', '2026-04-13T08:30:00+00:00', true],
                ['2026-04-13T09:00:00+00:00', '2026-04-13T09:30:00+00:00', true],
            ],
            self::rows($busy),
        );
    }

    public function testACurrentReadLeavesTheMovedAppointmentOut(): void
    {
        $busy = $this->reader->occupyingBetween(
            ClinicId::fromString(self::CLINIC_ID),
            UserId::fromString(self::VET),
            new \DateTimeImmutable('2026-04-13T08:00:00Z'),
            new \DateTimeImmutable('2026-04-13T12:00:00Z'),
            AppointmentId::fromString(self::MOVED),
            currentRead: true,
        );

        self::assertSame([['2026-04-13T08:00:00+00:00', '2026-04-13T08:30:00+00:00', true]], self::rows($busy));
    }

    /**
     * @param list<BusyInterval> $busy
     *
     * @return list<array{string, string, bool}>
     */
    private static function rows(array $busy): array
    {
        return array_map(
            static fn (BusyInterval $interval): array => [$interval->start->format('c'), $interval->end->format('c'), $interval->countsTowardsCapacity],
            $busy,
        );
    }

    private function appointment(string $id, string $startsAtUtc, int $minutes, AppointmentStatus $status, string $vet = self::VET): void
    {
        AppointmentEntityFactory::new()
            ->withId($id)
            ->withClinicId(self::CLINIC_ID)
            ->withPractitionerUserId($vet)
            ->startingAt(new \DateTimeImmutable($startsAtUtc, new \DateTimeZone('UTC')), $minutes)
            ->withStatus($status)
            ->create()
        ;
    }
}
