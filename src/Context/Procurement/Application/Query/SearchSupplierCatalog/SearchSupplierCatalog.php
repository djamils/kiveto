<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Query\SearchSupplierCatalog;

use App\Shared\Application\Bus\QueryInterface;

final readonly class SearchSupplierCatalog implements QueryInterface
{
    public function __construct(
        public string $term,
        public string $supplierId,
    ) {
    }
}
