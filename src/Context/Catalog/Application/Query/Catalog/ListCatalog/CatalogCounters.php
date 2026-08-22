<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Query\Catalog\ListCatalog;

/**
 * What the tabs of the catalogue page show next to their name: how much is on
 * offer, and how much has been put away.
 */
final readonly class CatalogCounters
{
    public function __construct(
        public int $activeActs,
        public int $archivedActs,
        public int $activeArticles,
        public int $archivedArticles,
        public int $activePackages,
        public int $archivedPackages,
        public int $priceLists,
    ) {
    }
}
