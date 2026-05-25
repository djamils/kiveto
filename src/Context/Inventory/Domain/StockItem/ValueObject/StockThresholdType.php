<?php

declare(strict_types=1);

namespace App\Context\Inventory\Domain\StockItem\ValueObject;

enum StockThresholdType: string
{
    case ABSOLUTE = 'ABSOLUTE';
    // DAYS_OF_STOCK deferred to V2
}
