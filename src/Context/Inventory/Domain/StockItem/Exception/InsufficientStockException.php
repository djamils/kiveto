<?php

declare(strict_types=1);

namespace App\Context\Inventory\Domain\StockItem\Exception;

final class InsufficientStockException extends \DomainException
{
    public function __construct(string $available, string $requested, string $unit)
    {
        parent::__construct(\sprintf('Insufficient stock: available %s %s, requested %s %s.', $available, $unit, $requested, $unit));
    }
}
