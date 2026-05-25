<?php

declare(strict_types=1);

namespace App\Context\Inventory\Domain\StockItem\ValueObject;

final readonly class StockThreshold
{
    public function __construct(
        private Quantity $quantity,
        private StockThresholdType $type,
    ) {
    }

    public function quantity(): Quantity
    {
        return $this->quantity;
    }

    public function type(): StockThresholdType
    {
        return $this->type;
    }

    public function equals(self $other): bool
    {
        // StockThresholdType currently has a single case; compare quantity only.
        return $this->quantity->equals($other->quantity);
    }
}
