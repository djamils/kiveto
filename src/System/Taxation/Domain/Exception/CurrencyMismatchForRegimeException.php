<?php

declare(strict_types=1);

namespace App\System\Taxation\Domain\Exception;

final class CurrencyMismatchForRegimeException extends TaxResolutionException
{
    public function __construct(string $regimeId, string $expected, string $actual)
    {
        parent::__construct(\sprintf(
            'Currency mismatch for regime "%s": expected "%s", got "%s".',
            $regimeId,
            $expected,
            $actual
        ));
    }
}
