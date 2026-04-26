<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Search;

final readonly class AnimalSearchIndexData
{
    public function __construct(
        public string $animalId,
        public string $clinicId,
        public string $animalName,
        public string $species,
        public ?string $breedName,
        public ?string $chipNumber,
        public ?string $ownerName,
        public ?string $ownerPhone,
        public ?string $primaryOwnerClientId,
        public string $status,
    ) {
    }
}
