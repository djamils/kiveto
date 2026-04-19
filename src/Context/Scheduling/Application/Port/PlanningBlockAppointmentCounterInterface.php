<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Port;

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
}
