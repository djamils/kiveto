<?php

declare(strict_types=1);

namespace App\Context\Inventory\Domain\StockItem\Exception;

final class UnitMismatchException extends \DomainException
{
    public function __construct(string $expected, string $actual)
    {
        parent::__construct(\sprintf('Unit mismatch: expected "%s", got "%s".', $expected, $actual));
    }
}
