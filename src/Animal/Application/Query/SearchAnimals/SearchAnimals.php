<?php

declare(strict_types=1);

namespace App\Animal\Application\Query\SearchAnimals;

use App\Shared\Application\Bus\QueryInterface;

final readonly class SearchAnimals implements QueryInterface
{
    public function __construct(
        public string $clinicId,
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
