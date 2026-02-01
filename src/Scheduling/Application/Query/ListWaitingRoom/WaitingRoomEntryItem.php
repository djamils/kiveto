<?php

declare(strict_types=1);

namespace App\Scheduling\Application\Query\ListWaitingRoom;

final readonly class WaitingRoomEntryItem
{
    public function __construct(
        public string $id,
        public string $clinicId,
        public string $origin,
        public string $arrivalMode,
        public ?string $linkedAppointmentId,
        public ?string $ownerId,
        public ?string $animalId,
        public ?string $foundAnimalDescription,
        public int $priority,
        public ?string $triageNotes,
        public string $status,
        public string $arrivedAtUtc,
        public ?string $calledAtUtc,
        public ?string $serviceStartedAtUtc,
        public ?string $closedAtUtc,
    ) {
    }
}
