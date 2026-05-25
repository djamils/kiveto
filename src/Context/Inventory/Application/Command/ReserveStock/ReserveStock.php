<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Command\ReserveStock;

final readonly class ReserveStock
{
    public function __construct(
        public string $stockItemId,
        public string $clinicId,
        public string $quantityAmount,
        public string $quantityUnit,
    ) {
    }
}
