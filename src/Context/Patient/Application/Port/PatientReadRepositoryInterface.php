<?php

declare(strict_types=1);

namespace App\Context\Patient\Application\Port;

interface PatientReadRepositoryInterface
{
    public function existsActiveForAnimal(string $clinicId, string $animalId): bool;
}
