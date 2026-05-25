<?php

declare(strict_types=1);

namespace App\Context\Inventory\Domain\StockAlert\ValueObject;

enum StockAlertSeverity: string
{
    case CRITICAL = 'CRITICAL';
    case WARNING  = 'WARNING';
    case INFO     = 'INFO';
}
