<?php

declare(strict_types=1);

namespace App\Context\Inventory\Domain\StockItem\Exception;

final class ConcurrentStockModificationException extends \RuntimeException
{
    public function __construct(string $stockItemId)
    {
        parent::__construct(\sprintf('Concurrent modification detected on StockItem "%s". Please retry the operation.', $stockItemId));
    }
}
