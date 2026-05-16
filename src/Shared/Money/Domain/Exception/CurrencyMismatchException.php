<?php

declare(strict_types=1);

namespace App\Shared\Money\Domain\Exception;

final class CurrencyMismatchException extends \DomainException
{
    public function __construct(string $from, string $to)
    {
        parent::__construct(\sprintf('Currency mismatch: cannot operate on "%s" and "%s".', $from, $to));
    }
}
