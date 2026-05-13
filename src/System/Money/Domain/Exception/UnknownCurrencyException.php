<?php

declare(strict_types=1);

namespace App\System\Money\Domain\Exception;

use App\Shared\Domain\ValueObject\CurrencyCode;

final class UnknownCurrencyException extends \DomainException
{
    public function __construct(CurrencyCode $code)
    {
        parent::__construct(\sprintf('Unknown currency: "%s".', $code->toString()));
    }
}
