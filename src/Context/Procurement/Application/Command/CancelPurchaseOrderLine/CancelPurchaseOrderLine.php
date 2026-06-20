<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Command\CancelPurchaseOrderLine;

use App\Shared\Application\Bus\CommandInterface;

final readonly class CancelPurchaseOrderLine implements CommandInterface
{
    public function __construct(
        public string $purchaseOrderId,
        public string $lineId,
        public string $reason,
    ) {
    }
}
