<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Command\ReceiveStock;

final readonly class ReceiveStock
{
    public function __construct(
        public string $stockItemId,
        public string $clinicId,
        public string $lotNumber,
        public string $quantityAmount,
        public string $quantityUnit,
        public string $expiryDate,
        public ?string $sourceSupplierId,
        public ?string $occurredAt,
        public ?string $reference,
        public ?string $performedBy,
    ) {
    }
}
