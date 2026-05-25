<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Query\GetStockItem;

final readonly class GetStockItem
{
    public function __construct(
        public string $stockItemId,
        public string $clinicId,
    ) {
    }
}
