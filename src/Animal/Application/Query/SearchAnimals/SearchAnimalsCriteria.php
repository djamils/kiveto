<?php

declare(strict_types=1);

namespace App\Animal\Application\Query\SearchAnimals;

final readonly class SearchAnimalsCriteria
{
    public function __construct(
        public ?string $searchTerm = null,
        public ?string $status = null,
        public ?string $species = null,
        public ?string $lifeStatus = null,
        public ?string $ownerClientId = null,
        public int $page = 1,
        public int $limit = 20,
    ) {
    }
}
