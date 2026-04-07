<?php

declare(strict_types=1);

namespace App\Scheduling\Application\Port;

use App\Scheduling\Domain\ValueObject\AppointmentId;
use App\Scheduling\Domain\ValueObject\ClinicId;
use App\Scheduling\Domain\ValueObject\TimeSlot;
use App\Scheduling\Domain\ValueObject\UserId;

interface AppointmentConflictCheckerInterface
{
    public function hasOverlap(
        ClinicId $clinicId,
        UserId $practitionerUserId,
        TimeSlot $timeSlot,
        ?AppointmentId $excludeAppointmentId = null,
    ): bool;
}
