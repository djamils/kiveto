<?php

declare(strict_types=1);

namespace App\Context\Inventory\Domain\StockItem\Exception;

final class ArchivedStockItemException extends \DomainException
{
    public function __construct(string $stockItemId)
    {
        parent::__construct(\sprintf('StockItem "%s" is archived and does not accept mutations.', $stockItemId));
    }
}
