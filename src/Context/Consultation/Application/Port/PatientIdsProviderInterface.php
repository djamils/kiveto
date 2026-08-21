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

    /**
     * Same resolution, batched over several animals.
     *
     * Feeds the clinic-wide consultation list, whose species and free-text
     * filters are expressed on animals but applied on the patient column.
     *
     * @param list<string> $animalIds
     *
     * @return list<string>
     */
    public function findPatientIdsForAnimals(array $animalIds, string $clinicId): array;
}
