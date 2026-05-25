<?php

declare(strict_types=1);

namespace App\Context\Inventory\Domain\StockItem\ValueObject;

final readonly class LotConsumption
{
    public function __construct(
        public readonly LotId $lotId,
        public readonly Quantity $quantity,
    ) {
    }
}
