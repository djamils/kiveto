<?php

declare(strict_types=1);

namespace App\Context\Inventory\Domain\StockItem\Exception;

final class LotRecalledException extends \DomainException
{
    public function __construct(string $lotNumber)
    {
        parent::__construct(\sprintf('Lot "%s" has been recalled and cannot be modified.', $lotNumber));
    }
}
