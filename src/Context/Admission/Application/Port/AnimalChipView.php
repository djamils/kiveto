<?php

declare(strict_types=1);

namespace App\Context\Admission\Application\Port;

final readonly class AnimalChipView
{
    public function __construct(
        public string $animalId,
        public string $animalName,
    ) {
    }
}
