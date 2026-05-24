<?php

declare(strict_types=1);

namespace App\Shared\Domain\UnitOfMeasure\Exception;

final class InvalidUnitOfMeasureException extends \DomainException
{
    public function __construct(string $code)
    {
        parent::__construct(\sprintf(
            'Invalid unit of measure code: "%s". Expected format: ^[A-Z][A-Z_]{0,16}$',
            $code,
        ));
    }
}
