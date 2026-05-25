<?php

declare(strict_types=1);

namespace App\Context\Inventory\Domain\StockItem\Exception;

final class LotAlreadyTerminatedException extends \DomainException
{
    public function __construct(string $lotNumber, string $status)
    {
        parent::__construct(\sprintf('Lot "%s" is already terminated (status: %s) and cannot receive new stock.', $lotNumber, $status));
    }
}
