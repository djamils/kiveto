<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Command\ClosePurchaseOrder;

use App\Shared\Application\Bus\CommandInterface;

final readonly class ClosePurchaseOrder implements CommandInterface
{
    public function __construct(public string $purchaseOrderId)
    {
    }
}
