<?php

declare(strict_types=1);

namespace App\Context\Inventory\Domain\StockItem\Exception;

final class NegativeCoefficientException extends \DomainException
{
    public function __construct(string $coefficient)
    {
        parent::__construct(\sprintf('Coefficient must be positive, got "%s".', $coefficient));
    }
}
