<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Command\SubmitPurchaseOrder;

use App\Shared\Application\Bus\CommandInterface;

final readonly class SubmitPurchaseOrder implements CommandInterface
{
    public function __construct(
        public string $purchaseOrderId,
        public string $clinicId,
    ) {
    }
}
