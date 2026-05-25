<?php

declare(strict_types=1);

namespace App\Context\Inventory\Domain\StockItem\Exception;

final class StockNotEmptyOnArchiveException extends \DomainException
{
    public function __construct(string $stockItemId, string $totalOnHand)
    {
        parent::__construct(\sprintf('Cannot archive StockItem "%s": totalOnHand is %s (must be zero).', $stockItemId, $totalOnHand));
    }
}
