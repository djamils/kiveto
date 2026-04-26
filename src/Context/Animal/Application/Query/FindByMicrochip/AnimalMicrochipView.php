<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Query\FindByMicrochip;

final readonly class AnimalMicrochipView
{
    public function __construct(
        public string $animalId,
        public string $clinicId,
        public string $name,
    ) {
    }
}
