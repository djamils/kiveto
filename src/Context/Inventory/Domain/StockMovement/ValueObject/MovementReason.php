<?php

declare(strict_types=1);

namespace App\Context\Inventory\Domain\StockMovement\ValueObject;

enum MovementReason: string
{
    // IN
    case RECEIPT_SUPPLIER           = 'RECEIPT_SUPPLIER';
    case RECEIPT_OPENING            = 'RECEIPT_OPENING';
    case RECEIPT_RETURN_FROM_CLIENT = 'RECEIPT_RETURN_FROM_CLIENT';

    // OUT
    case CONSUMPTION_CONSULTATION  = 'CONSUMPTION_CONSULTATION';
    case SALE_RETAIL               = 'SALE_RETAIL';
    case LOSS_EXPIRY               = 'LOSS_EXPIRY';
    case LOSS_BREAKAGE             = 'LOSS_BREAKAGE';
    case LOSS_THEFT                = 'LOSS_THEFT';
    case RECALL_RETURN_TO_SUPPLIER = 'RECALL_RETURN_TO_SUPPLIER';

    // ADJUSTMENT
    case PHYSICAL_INVENTORY = 'PHYSICAL_INVENTORY';
    case CORRECTION         = 'CORRECTION';
}
