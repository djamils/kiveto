<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Query\Pricing\ListPriceLists;

use App\Shared\Application\Bus\QueryInterface;

final readonly class ListPriceLists implements QueryInterface
{
    public function __construct(
        public string $clinicId,
    ) {
    }
}
