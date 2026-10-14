<?php

declare(strict_types=1);

namespace App\Context\Inventory\Domain\StockItem\ValueObject;

/**
 * Where a clinic keeps an article. A handful of places, the same in every
 * clinic: they decide where the count starts, what goes in the fridge and what
 * stays under key.
 */
enum StorageLocation: string
{
    case PHARMACY       = 'PHARMACY';
    case FRIDGE         = 'FRIDGE';
    case NARCOTICS_SAFE = 'NARCOTICS_SAFE';
    case OPERATING_ROOM = 'OPERATING_ROOM';
    case RECEPTION      = 'RECEPTION';
}
