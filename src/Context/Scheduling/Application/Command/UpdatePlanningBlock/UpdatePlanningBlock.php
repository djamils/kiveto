<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\UpdatePlanningBlock;

use App\Shared\Application\Bus\CommandInterface;

final readonly class UpdatePlanningBlock implements CommandInterface
{
    public function __construct(
        public string $blockId,
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
    ) {
    }
}
