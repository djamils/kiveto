<?php

declare(strict_types=1);

namespace App\System\Taxation\Domain\Exception;

final class UnknownTaxRegimeException extends TaxResolutionException
{
    public function __construct(string $regimeId)
    {
        parent::__construct(\sprintf('Unknown or inactive tax regime: "%s".', $regimeId));
    }
}
