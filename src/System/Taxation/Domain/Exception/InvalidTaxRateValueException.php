<?php

declare(strict_types=1);

namespace App\System\Taxation\Domain\Exception;

final class InvalidTaxRateValueException extends \DomainException
{
    public function __construct(int $basisPoints)
    {
        parent::__construct(\sprintf(
            'Invalid tax rate value: %d basis points. Must be between 0 and 10000.',
            $basisPoints
        ));
    }
}
