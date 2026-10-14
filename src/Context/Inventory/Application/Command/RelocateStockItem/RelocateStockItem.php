<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Command\RelocateStockItem;

final readonly class RelocateStockItem
{
    /**
     * @param string|null $location a StorageLocation value, null when unknown
     */
    public function __construct(
        public string $stockItemId,
        public string $clinicId,
        public ?string $location,
    ) {
    }
}
