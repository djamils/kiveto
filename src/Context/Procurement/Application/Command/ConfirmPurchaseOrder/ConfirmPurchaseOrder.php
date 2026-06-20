<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Command\ConfirmPurchaseOrder;

use App\Shared\Application\Bus\CommandInterface;

final readonly class ConfirmPurchaseOrder implements CommandInterface
{
    public function __construct(
        public string $purchaseOrderId,
        public string $clinicId,
    ) {
    }
}
