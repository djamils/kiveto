<?php

declare(strict_types=1);

namespace App\ClinicalCare\Application\Port;

use App\ClinicalCare\Domain\ValueObject\AnimalId;

interface AnimalExistenceCheckerInterface
{
    public function exists(AnimalId $animalId): bool;
}
