<?php

declare(strict_types=1);

namespace App\Context\Inventory\Domain\StockItem\ValueObject;

enum StockItemStatus: string
{
    case ACTIVE   = 'ACTIVE';
    case ARCHIVED = 'ARCHIVED';
}
