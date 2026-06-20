<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\PurchaseOrder\ValueObject;

use App\Shared\Money\Domain\ValueObject\Money;

final readonly class PurchaseOrderTotals
{
    public function __construct(public readonly Money $subtotal)
    {
    }
}
