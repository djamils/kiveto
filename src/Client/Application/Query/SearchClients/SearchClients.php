<?php

declare(strict_types=1);

namespace App\Client\Application\Query\SearchClients;

final readonly class SearchClients
{
    public function __construct(
        public string $clinicId,
        public ?string $searchTerm = null,
        public ?string $status = null,
        public int $page = 1,
        public int $limit = 20,
    ) {
    }
}
