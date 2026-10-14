<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\ListPlanningBlocksForClinicDateRange;

/**
 * One occurrence of a planning block: `date` is the day it falls on,
 * `baseDate` the day its series is anchored on (the same for a one-off block).
 */
final readonly class PlanningBlockView
{
    public function __construct(
        public string $id,
        public string $clinicId,
        public string $staffUserId,
        public string $type,
        public string $date,
        public string $startTime,
        public string $endTime,
        public int $capacityPerHour,
        public string $recurrenceFreq,
        public ?string $recurrenceUntil,
        public ?string $note,
        public bool $acceptsAppointments,
        public bool $hasCapacityLimit,
        public string $baseDate,
    ) {
    }
}
