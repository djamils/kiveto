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
        public int $basePriceMinorUnits,
        public string $basePriceCurrency,
        /** 'incl' (all taxes included) or 'excl': the mode the price was entered in. */
        public string $basePriceMode,
        public string $taxCategoryCode,
        public bool $requiresPrescription,
        /** DRUG, CONSUMABLE, FOOD or EQUIPMENT for an article, null for an act. */
        public ?string $articleKind = null,
    ) {
    }
}
