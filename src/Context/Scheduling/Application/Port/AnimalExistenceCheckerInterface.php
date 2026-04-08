<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Port;

use App\Context\Scheduling\Domain\ValueObject\AnimalId;

interface AnimalExistenceCheckerInterface
{
    public function exists(AnimalId $animalId): bool;
}
