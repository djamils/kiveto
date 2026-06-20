<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Service;

use App\Context\Procurement\Domain\PurchaseOrder\PurchaseOrder;
use App\Shared\Money\Domain\ValueObject\Money;

final class PurchaseOrderTotalsCalculator
{
    public function calculateSubtotal(PurchaseOrder $order): Money
    {
        return $order->totalAmount();
    }
}
