<?php

declare(strict_types=1);

namespace App\Shared\Domain\UnitOfMeasure\ValueObject;

enum UnitCategory: string
{
    case COUNT  = 'COUNT';
    case MASS   = 'MASS';
    case VOLUME = 'VOLUME';
    case TIME   = 'TIME';
    case OTHER  = 'OTHER';
}
