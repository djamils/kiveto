<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Port;

use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\TimeSlot;
use App\Context\Scheduling\Domain\ValueObject\UserId;

interface AppointmentConflictCheckerInterface
{
    public function hasOverlap(
        ClinicId $clinicId,
        UserId $practitionerUserId,
        TimeSlot $timeSlot,
        ?AppointmentId $excludeAppointmentId = null,
    ): bool;

    /**
     * Same check as a current read (`FOR SHARE`): sees what another transaction
     * committed after this one's snapshot. Call it under the practitioner-day lock.
     */
    public function hasOverlapLocked(
        ClinicId $clinicId,
        UserId $practitionerUserId,
        TimeSlot $timeSlot,
        ?AppointmentId $excludeAppointmentId = null,
    ): bool;
}
