<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Exception;

final class ClinicCurrencyMismatchException extends \DomainException
{
    public function __construct(string $expected, string $actual)
    {
        parent::__construct(\sprintf(
            'Currency mismatch: clinic uses "%s" but received "%s".',
            $expected,
            $actual,
        ));
    }
}
