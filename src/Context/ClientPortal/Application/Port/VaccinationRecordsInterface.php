<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** The vaccinations of an animal (Animal context). */
interface VaccinationRecordsInterface
{
    /**
     * Every vaccination of the animal, latest first.
     *
     * @return list<VaccinationRecord>
     */
    public function vaccinationsOf(string $clinicId, string $animalId): array;
}
