<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Command\RestoreStockItem;

final readonly class RestoreStockItem
{
    public function __construct(
        public string $stockItemId,
        public string $clinicId,
    ) {
    }
}
