<?php

declare(strict_types=1);

namespace App\Shared\Domain\UnitOfMeasure\Exception;

final class UnknownUnitOfMeasureException extends \DomainException
{
    public function __construct(string $code)
    {
        parent::__construct(\sprintf(
            'Unknown unit of measure: "%s". Register it in the units catalogue.',
            $code,
        ));
    }
}
