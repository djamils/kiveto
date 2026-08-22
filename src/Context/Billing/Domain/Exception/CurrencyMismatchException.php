<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\Exception;

final class CurrencyMismatchException extends \DomainException
{
    public function __construct(string $expected, string $given)
    {
        parent::__construct(\sprintf('Currency mismatch: the invoice is in %s, got %s.', $expected, $given));
    }
}
