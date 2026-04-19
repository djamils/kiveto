<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\ListPlanningBlocksForClinicDateRange;

final readonly class PlanningBlockView
{
    public function __construct(
        public string $id,
        public string $clinicId,
        public string $practitionerUserId,
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
    ) {
    }
}
