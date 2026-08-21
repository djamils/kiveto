<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\SearchClients;

final readonly class SearchClients
{
    public function __construct(
        public string $clinicId,
        public ?string $searchTerm = null,
        public ?string $status = null,
        public int $page = 1,
        public int $limit = 20,
        /** @var list<string> statuses to keep, empty means all */
        public array $statuses = [],
        /** @var list<string> cities to keep, empty means all */
        public array $cities = [],
        public string $sort = 'name',
        public string $direction = 'asc',
    ) {
    }
}
