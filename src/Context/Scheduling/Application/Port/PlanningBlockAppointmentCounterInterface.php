<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Port;

use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\UserId;

interface PlanningBlockAppointmentCounterInterface
{
    public function countActiveInWindow(
        ClinicId $clinicId,
        UserId $staffUserId,
        \DateTimeImmutable $windowStartUtc,
        \DateTimeImmutable $windowEndUtc,
    ): int;

    /**
     * Occupying appointments of the window as a current read (`FOR SHARE`),
     * optionally without the one being moved. Call it under the
     * practitioner-day lock.
     */
    public function countOccupyingInWindowLocked(
        ClinicId $clinicId,
        UserId $staffUserId,
        \DateTimeImmutable $windowStartUtc,
        \DateTimeImmutable $windowEndUtc,
        ?AppointmentId $excludeAppointmentId = null,
    ): int;
}
