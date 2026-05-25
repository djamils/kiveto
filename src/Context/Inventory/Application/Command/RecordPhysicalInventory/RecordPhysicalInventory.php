<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Command\RecordPhysicalInventory;

final readonly class RecordPhysicalInventory
{
    public function __construct(
        public string $stockItemId,
        public string $clinicId,
        public string $lotId,
        public string $newLotQuantityAmount,
        public string $newLotQuantityUnit,
        public string $reason = 'PHYSICAL_INVENTORY',
        public ?string $occurredAt = null,
        public ?string $performedBy = null,
    ) {
    }
}
