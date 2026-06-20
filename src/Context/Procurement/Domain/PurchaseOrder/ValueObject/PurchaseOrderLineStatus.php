<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\PurchaseOrder\ValueObject;

enum PurchaseOrderLineStatus: string
{
    case ACTIVE         = 'ACTIVE';
    case FULLY_RECEIVED = 'FULLY_RECEIVED';
    case CANCELLED      = 'CANCELLED';
}
