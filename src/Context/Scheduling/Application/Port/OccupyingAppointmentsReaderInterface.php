<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Port;

use App\Context\Scheduling\Domain\Booking\BusyInterval;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\UserId;

interface OccupyingAppointmentsReaderInterface
{
    /**
     * The practitioner's appointments that occupy their slot (planned or
     * pending) and overlap [fromUtc, toUtc), by start. `excludeAppointmentId`
     * leaves out an appointment being moved; `currentRead` reads `FOR SHARE`,
     * for a check under the practitioner-day lock.
     *
     * @return list<BusyInterval>
     */
    public function occupyingBetween(
        ClinicId $clinicId,
        UserId $practitionerUserId,
        \DateTimeImmutable $fromUtc,
        \DateTimeImmutable $toUtc,
        ?AppointmentId $excludeAppointmentId = null,
        bool $currentRead = false,
    ): array;
}
