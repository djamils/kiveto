<?php

declare(strict_types=1);

namespace App\Context\Admission\Application\Port;

final readonly class WaitingRoomItemDto
{
    public function __construct(
        public string $admissionId,
        public string $clinicId,
        public string $patientId,
        public string $displayLabel,
        public string $triageLevel,
        public string $locationStatus,
        public string $intakeChannel,
        public string $openedAt,
        public ?string $triageNotes,
        public bool $isPatientIdentifiedAtOpening = true,
        public ?string $knownAnimalId = null,
        public ?string $physicalDescription = null,
        public ?string $practitionerLabel = null,
        public ?string $appointmentReason = null,
        public ?string $contextNote = null,
        public ?string $practitionerUserId = null,
    ) {
    }
}
