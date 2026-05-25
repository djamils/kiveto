<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Command\ArchiveStockItem;

final readonly class ArchiveStockItem
{
    public function __construct(
        public string $stockItemId,
        public string $clinicId,
    ) {
    }
}
