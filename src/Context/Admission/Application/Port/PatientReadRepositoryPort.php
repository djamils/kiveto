<?php

declare(strict_types=1);

namespace App\Context\Admission\Application\Port;

interface PatientReadRepositoryPort
{
    public function existsActiveForAnimal(string $clinicId, string $animalId): bool;

    public function getActivePatientIdForAnimal(string $clinicId, string $animalId): ?string;
}
