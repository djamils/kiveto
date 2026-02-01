<?php

declare(strict_types=1);

namespace App\Scheduling\Application\Command\CreateWaitingRoomWalkInEntry;

use App\Shared\Application\Bus\CommandInterface;

final readonly class CreateWaitingRoomWalkInEntry implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public ?string $ownerId = null,
        public ?string $animalId = null,
        public ?string $foundAnimalDescription = null,
        public string $arrivalMode = 'STANDARD', // STANDARD | EMERGENCY
        public int $priority = 0,
        public ?string $triageNotes = null,
    ) {
    }
}
