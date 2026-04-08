<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\UpdateWaitingRoomTriage;

use App\Shared\Application\Bus\CommandInterface;

final readonly class UpdateWaitingRoomTriage implements CommandInterface
{
    public function __construct(
        public string $waitingRoomEntryId,
        public int $priority,
        public ?string $triageNotes,
        public string $arrivalMode, // STANDARD | EMERGENCY
    ) {
    }
}
