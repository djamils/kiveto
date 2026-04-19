<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Port;

use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockId;
use App\Context\Scheduling\Domain\ValueObject\UserId;

interface PlanningBlockOverlapCheckerInterface
{
    public function hasOverlap(
        ClinicId $clinicId,
        UserId $staffUserId,
        string $date,
        string $startTime,
        string $endTime,
        ?PlanningBlockId $excludeId = null,
    ): bool;
}
