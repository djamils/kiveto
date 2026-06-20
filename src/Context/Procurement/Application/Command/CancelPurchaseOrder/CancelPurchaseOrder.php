<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Command\CancelPurchaseOrder;

use App\Shared\Application\Bus\CommandInterface;

final readonly class CancelPurchaseOrder implements CommandInterface
{
    public function __construct(
        public string $purchaseOrderId,
        public string $reason,
    ) {
    }
}
