<?php

declare(strict_types=1);

namespace App\Animal\Application\Query\SearchAnimals;

final readonly class AnimalListItemView
{
    public function __construct(
        public string $id,
        public string $name,
        public string $species,
        public string $sex,
        public ?string $breedName,
        public ?string $birthDate,
        public ?string $photoUrl,
        public string $status,
        public string $lifeStatus,
        public ?string $primaryOwnerClientId,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
