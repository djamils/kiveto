<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Port;

/**
 * Patient → animal (Patient context) → active primary owner (Animal
 * context), ids only: who a consultation report may be shared with.
 */
interface PatientAnimalOwnershipInterface
{
    public function ownershipOf(string $clinicId, string $patientId): PatientAnimalOwnership;
}
