<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Port;

use App\Context\Consultation\Domain\ValueObject\AnimalId;

interface AnimalExistenceCheckerInterface
{
    public function exists(AnimalId $animalId): bool;
}
