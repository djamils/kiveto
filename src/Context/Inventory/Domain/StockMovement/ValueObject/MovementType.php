<?php

declare(strict_types=1);

namespace App\Context\Inventory\Domain\StockMovement\ValueObject;

enum MovementType: string
{
    case IN         = 'IN';
    case OUT        = 'OUT';
    case ADJUSTMENT = 'ADJUSTMENT';
}
