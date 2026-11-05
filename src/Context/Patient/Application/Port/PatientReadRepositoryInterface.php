<?php

declare(strict_types=1);

namespace App\Context\Patient\Application\Port;

use App\Context\Patient\Application\Query\GetPatientAnimalLink\PatientAnimalLinkDto;

interface PatientReadRepositoryInterface
{
    public function existsActiveForAnimal(string $clinicId, string $animalId): bool;

    public function getActivePatientIdForAnimal(string $clinicId, string $animalId): ?string;

    public function findAnimalLink(string $clinicId, string $patientId): ?PatientAnimalLinkDto;

    /**
     * The links of several patients of the clinic in one query, by patient
     * id; an unknown patient, or another clinic's, is left out.
     *
     * @param non-empty-list<string> $patientIds UUID strings
     *
     * @return array<string, PatientAnimalLinkDto>
     */
    public function findAnimalLinks(string $clinicId, array $patientIds): array;
}
