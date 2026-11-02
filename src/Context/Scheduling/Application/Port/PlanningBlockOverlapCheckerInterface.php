<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Port;

use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockId;
use App\Context\Scheduling\Domain\ValueObject\RecurrenceRule;
use App\Context\Scheduling\Domain\ValueObject\TimeRange;
use App\Context\Scheduling\Domain\ValueObject\UserId;

interface PlanningBlockOverlapCheckerInterface
{
    /**
     * Whether the block (its occurrences, `PlanningBlockOverlap`) meets an
     * occurrence of another block of the staff member.
     *
     * @param string $todayLocal the clinic's today (YYYY-MM-DD)
     */
    public function hasOverlap(
        ClinicId $clinicId,
        UserId $staffUserId,
        TimeRange $timeRange,
        RecurrenceRule $rule,
        string $todayLocal,
        ?PlanningBlockId $excludeId = null,
    ): bool;
}
