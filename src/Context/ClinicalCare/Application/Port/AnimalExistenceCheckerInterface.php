<?php

declare(strict_types=1);

namespace App\Context\ClinicalCare\Application\Port;

use App\Context\ClinicalCare\Domain\ValueObject\AnimalId;

interface AnimalExistenceCheckerInterface
{
    public function exists(AnimalId $animalId): bool;
}
