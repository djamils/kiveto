<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\CreatePlanningBlock;

use App\Shared\Application\Bus\CommandInterface;

final readonly class CreatePlanningBlock implements CommandInterface
{
    public function __construct(
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
    ) {
    }
}
