<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Query\Catalog\SearchCatalogItems;

final readonly class CatalogSearchResult
{
    public function __construct(
        public string $id,
        public string $type,
        public string $name,
        public string $code,
        public string $status,
    ) {
    }
}
