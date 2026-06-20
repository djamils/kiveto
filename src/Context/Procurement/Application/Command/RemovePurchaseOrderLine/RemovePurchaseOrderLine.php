<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Command\RemovePurchaseOrderLine;

use App\Shared\Application\Bus\CommandInterface;

final readonly class RemovePurchaseOrderLine implements CommandInterface
{
    public function __construct(
        public string $purchaseOrderId,
        public string $lineId,
    ) {
    }
}
