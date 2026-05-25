<?php

declare(strict_types=1);

namespace App\Context\Inventory\Domain\StockItem\Exception;

final class NegativeQuantityException extends \DomainException
{
    public function __construct()
    {
        parent::__construct('Quantity cannot be negative.');
    }
}
