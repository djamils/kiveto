<?php

declare(strict_types=1);

namespace App\Context\Inventory\Domain\StockItem\Exception;

final class StockItemNotFoundException extends \DomainException
{
    public function __construct(string $stockItemId)
    {
        parent::__construct(\sprintf('StockItem "%s" not found.', $stockItemId));
    }
}
