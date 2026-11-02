<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Support;

use App\Context\Scheduling\Application\Port\OccupyingAppointmentsReaderInterface;
use App\Context\Scheduling\Domain\Booking\BusyInterval;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\UserId;

/**
 * Occupying appointments per practitioner; every read is recorded as
 * "<practitioner>|<from UTC>|<to UTC>|<current read>".
 */
final class FakeOccupyingAppointments implements OccupyingAppointmentsReaderInterface
{
    /** @var list<string> */
    public array $reads = [];
    /** @var array<string, list<BusyInterval>> */
    private array $byPractitioner = [];

    /**
     * @param string $startsAtUtc e.g. '2026-06-02 07:00' (09:00 in Paris)
     */
    public function add(string $practitionerId, string $startsAtUtc, int $minutes = 30): void
    {
        $start = new \DateTimeImmutable($startsAtUtc, new \DateTimeZone('UTC'));

        $this->byPractitioner[$practitionerId][] = BusyInterval::appointment($start, $start->modify(\sprintf('+%d minutes', $minutes)));
    }

    public function occupyingBetween(
        ClinicId $clinicId,
        UserId $practitionerUserId,
        \DateTimeImmutable $fromUtc,
        \DateTimeImmutable $toUtc,
        ?AppointmentId $excludeAppointmentId = null,
        bool $currentRead = false,
    ): array {
        $this->reads[] = \sprintf('%s|%s|%s|%s', $practitionerUserId->toString(), $fromUtc->format('Y-m-d H:i'), $toUtc->format('Y-m-d H:i'), $currentRead ? 'current' : 'snapshot');

        return array_values(array_filter(
            $this->byPractitioner[$practitionerUserId->toString()] ?? [],
            static fn (BusyInterval $busy): bool => $busy->overlaps($fromUtc, $toUtc),
        ));
    }
}
