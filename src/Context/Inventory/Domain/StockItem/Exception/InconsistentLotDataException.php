<?php

declare(strict_types=1);

namespace App\Context\Inventory\Domain\StockItem\Exception;

final class InconsistentLotDataException extends \DomainException
{
    public function __construct(string $lotNumber)
    {
        parent::__construct(\sprintf('Inconsistent data for lot "%s": an ACTIVE lot with the same number exists but with a different expiry date.', $lotNumber));
    }
}
