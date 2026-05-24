<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Query\Catalog\SearchCatalogItems;

use App\Shared\Application\Bus\QueryInterface;

final readonly class SearchCatalogItems implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $term,
        public int $limit = 20,
    ) {
    }
}
