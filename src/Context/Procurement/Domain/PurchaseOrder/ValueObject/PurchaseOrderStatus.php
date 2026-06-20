<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\PurchaseOrder\ValueObject;

enum PurchaseOrderStatus: string
{
    case DRAFT              = 'DRAFT';
    case SUBMITTING         = 'SUBMITTING';
    case SUBMITTED          = 'SUBMITTED';
    case CONFIRMED          = 'CONFIRMED';
    case PARTIALLY_RECEIVED = 'PARTIALLY_RECEIVED';
    case RECEIVED           = 'RECEIVED';
    case CLOSED             = 'CLOSED';
    case CANCELLED          = 'CANCELLED';
    case SEND_FAILED        = 'SEND_FAILED';
}
