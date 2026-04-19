<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Query\ListAnimalSummariesPerClientIds;

final readonly class AnimalSummary
{
    public function __construct(
        public string $id,
        public string $name,
        public string $species,
    ) {
    }
}
