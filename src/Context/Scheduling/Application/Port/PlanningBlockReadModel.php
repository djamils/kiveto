<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Port;

use App\Context\Scheduling\Domain\ValueObject\PlanningBlockType;

final readonly class PlanningBlockReadModel
{
    public function __construct(
        public string $id,
        public PlanningBlockType $type,
        public int $capacityPerHour,
        public int $currentAppointmentCount,
        public bool $acceptsAppointments,
    ) {
    }
}
