<?php

declare(strict_types=1);

namespace App\Scheduling\Application\Port;

use App\Scheduling\Domain\ValueObject\AnimalId;

interface AnimalExistenceCheckerInterface
{
    public function exists(AnimalId $animalId): bool;
}
