<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Search;

final readonly class AnimalSearchRow
{
    public function __construct(
        public string $id,
        public string $animalName,
        public string $species,
        public ?string $breedName,
        public ?string $searchOwnerName,
        public ?string $primaryOwnerClientId,
        public string $status,
    ) {
    }
}
