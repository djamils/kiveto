<?php

declare(strict_types=1);

namespace App\Context\Patient\Application\Port;

interface AnimalNameProviderInterface
{
    public function getAnimalName(string $animalId, string $clinicId): ?string;
}
