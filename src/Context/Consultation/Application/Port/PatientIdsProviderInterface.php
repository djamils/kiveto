<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Port;

interface PatientIdsProviderInterface
{
    /**
     * Every patient record of a clinic linked to the same animal.
     *
     * Reconciliation can leave several patient rows behind for one animal, so
     * the consultation history must be keyed on all of them, not just the one
     * carried by the current consultation.
     *
     * @return list<string>
     */
    public function findPatientIdsForAnimal(string $animalId, string $clinicId): array;
}
