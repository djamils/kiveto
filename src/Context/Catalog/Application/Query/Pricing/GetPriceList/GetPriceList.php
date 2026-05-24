<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Query\Pricing\GetPriceList;

use App\Shared\Application\Bus\QueryInterface;

final readonly class GetPriceList implements QueryInterface
{
    public function __construct(
        public string $priceListId,
        public string $clinicId,
    ) {
    }
}
