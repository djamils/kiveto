<?php

declare(strict_types=1);

namespace App\Client\Application\Query\SearchClients;

final readonly class SearchClientsCriteria
{
    public function __construct(
        public ?string $searchTerm = null,
        public ?string $status = null,
        public int $page = 1,
        public int $limit = 20,
    ) {
        if ($page < 1) {
            throw new \InvalidArgumentException('Page must be >= 1.');
        }

        if ($limit < 1 || $limit > 100) {
            throw new \InvalidArgumentException('Limit must be between 1 and 100.');
        }
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->limit;
    }
}
