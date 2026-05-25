<?php

declare(strict_types=1);

namespace App\Context\Inventory\Domain\StockItem\ValueObject;

enum LotStatus: string
{
    case ACTIVE   = 'ACTIVE';
    case EXPIRED  = 'EXPIRED';
    case RECALLED = 'RECALLED';
    case DEPLETED = 'DEPLETED';
}
