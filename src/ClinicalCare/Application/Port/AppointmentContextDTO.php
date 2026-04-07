<?php

declare(strict_types=1);

namespace App\ClinicalCare\Application\Port;

final readonly class AppointmentContextDTO
{
    public function __construct(
        public string $clinicId,
        public ?string $linkedWaitingRoomEntryId,
        public ?string $ownerId,
        public ?string $animalId,
        public ?string $arrivalMode, // STANDARD | EMERGENCY
        public string $status, // PLANNED, etc.
    ) {
    }
}
