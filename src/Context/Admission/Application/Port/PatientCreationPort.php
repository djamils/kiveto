<?php

declare(strict_types=1);

namespace App\Context\Admission\Application\Port;

interface PatientCreationPort
{
    /**
     * Creates an unidentified patient record via the Patient BC.
     * Returns the new patient ID.
     */
    public function createUnidentifiedPatient(
        string $clinicId,
        string $provisionalLabel,
        ?string $observedSpecies,
        ?string $observedColor,
    ): string;

    /**
     * Creates an identified patient record linked to an existing animal via the Patient BC.
     * Returns the new patient ID.
     */
    public function createIdentifiedPatient(
        string $clinicId,
        string $animalId,
        string $animalName,
    ): string;

    public function reconcilePatientToAnimal(
        string $sourcePatientId,
        string $animalId,
        string $animalName,
        string $clinicId,
    ): void;
}
