<?php

declare(strict_types=1);

namespace App\Context\Inventory\Domain\StockAlert\ValueObject;

enum StockAlertType: string
{
    case LOW_EXPIRY   = 'LOW_EXPIRY';
    case LOW_STOCK    = 'LOW_STOCK';
    case OUT_OF_STOCK = 'OUT_OF_STOCK';
}
