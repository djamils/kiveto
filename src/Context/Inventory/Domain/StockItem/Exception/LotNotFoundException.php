<?php

declare(strict_types=1);

namespace App\Context\Inventory\Domain\StockItem\Exception;

final class LotNotFoundException extends \DomainException
{
    public function __construct(string $lotId)
    {
        parent::__construct(\sprintf('Lot "%s" not found on this StockItem.', $lotId));
    }
}
