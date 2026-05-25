<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Query\GetStockItem;

final readonly class LotView
{
    public function __construct(
        public string $id,
        public string $number,
        public string $quantityAmount,
        public string $quantityUnit,
        public string $expiryDate,
        public string $status,
    ) {
    }
}
