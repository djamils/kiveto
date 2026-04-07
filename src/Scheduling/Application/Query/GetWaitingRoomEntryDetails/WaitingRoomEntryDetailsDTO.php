<?php

declare(strict_types=1);

namespace App\Scheduling\Application\Query\GetWaitingRoomEntryDetails;

/**
 * DTO: Waiting room entry details for cross-BC consumption.
 */
final readonly class WaitingRoomEntryDetailsDTO
{
    public function __construct(
        public string $waitingRoomEntryId,
        public string $clinicId,
        public string $status,
        public string $origin,
        public string $arrivalMode,
        public ?string $linkedAppointmentId,
        public ?string $ownerId,
        public ?string $animalId,
        public ?string $triageNotes,
        public string $arrivedAtUtc,
        public ?string $calledAtUtc,
        public ?string $serviceStartedAtUtc,
        public ?string $closedAtUtc,
    ) {
    }
}
