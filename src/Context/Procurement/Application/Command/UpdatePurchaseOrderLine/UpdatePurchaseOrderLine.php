<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Command\UpdatePurchaseOrderLine;

use App\Shared\Application\Bus\CommandInterface;

final readonly class UpdatePurchaseOrderLine implements CommandInterface
{
    public function __construct(
        public string $purchaseOrderId,
        public string $lineId,
        public string $orderedAmount,
        public int $unitPriceMinor,
        public string $unitPriceCurrency,
        public ?string $note,
    ) {
    }
}
