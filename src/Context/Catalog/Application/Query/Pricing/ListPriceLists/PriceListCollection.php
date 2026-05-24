<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Query\Pricing\ListPriceLists;

final readonly class PriceListCollection
{
    /** @param list<PriceListSummary> $items */
    public function __construct(
        public array $items,
    ) {
    }
}
