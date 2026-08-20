<?php

declare(strict_types=1);

namespace App\Context\Patient\Application\Query\GetPatientAnimalLink;

/**
 * Read model for the patient -> animal hop.
 *
 * animalId is null for unreconciled patients; observedSpecies/observedColor
 * then carry the provisional description captured at admission.
 */
final readonly class PatientAnimalLinkDto
{
    public function __construct(
        public string $patientId,
        public ?string $animalId,
        public string $displayLabel,
        public ?string $observedSpecies,
        public ?string $observedColor,
    ) {
    }
}
