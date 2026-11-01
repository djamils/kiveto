<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Port;

use App\Context\Scheduling\Domain\ValueObject\PlanningBlockType;

/**
 * One dated occurrence of a planning block, its recurrence expanded.
 */
final readonly class PlanningBlockOccurrence
{
    public function __construct(
        public string $date,
        public string $startTime,
        public string $endTime,
        public PlanningBlockType $type,
        public int $capacityPerHour,
    ) {
    }
}
