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
        public ?string $closedAt = null,
        public ?string $closureReason = null,
        public ?string $locationSince = null,
        // Who brought an emergency in (owner, third party, emergency services).
        public ?string $presenterName = null,
        public ?string $presenterPhone = null,
        public ?string $presenterRole = null,
        public ?ArrivalCheckInView $arrivalCheckIn = null,
        public ?string $scannedMicrochip = null,
        // The honoured booking, when the visit comes from one.
        public ?string $appointmentId = null,
        public ?string $appointmentStartsAt = null,
        // What the desk saw of an unidentified patient (« Chat », « Chien »).
        public ?string $observedSpecies = null,
    ) {
    }
}
