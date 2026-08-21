<?php

declare(strict_types=1);

namespace App\Context\Patient\Application\Port;

use App\Context\Patient\Application\Query\GetPatientAnimalLink\PatientAnimalLinkDto;

interface PatientReadRepositoryInterface
{
    public function existsActiveForAnimal(string $clinicId, string $animalId): bool;

    public function getActivePatientIdForAnimal(string $clinicId, string $animalId): ?string;

    public function findAnimalLink(string $clinicId, string $patientId): ?PatientAnimalLinkDto;
}
