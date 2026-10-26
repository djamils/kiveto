<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Exception;

final class InvalidPriceEntryModeException extends \DomainException
{
    public function __construct(string $mode)
    {
        parent::__construct(\sprintf('Invalid price entry mode "%s": expected "incl" or "excl".', $mode));
    }
}
