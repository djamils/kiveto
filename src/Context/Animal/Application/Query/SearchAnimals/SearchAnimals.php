<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Query\SearchAnimals;

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
        /** @var list<string> species to keep, empty means all */
        public array $speciesList = [],
        /** @var list<string> life statuses to keep, empty means all */
        public array $lifeStatuses = [],
        /** @var ?list<string> animals the caller narrowed down, null when unrestricted */
        public ?array $restrictToIds = null,
        public string $sort = 'name',
        public string $direction = 'asc',
    ) {
    }
}
