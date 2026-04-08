<?php

declare(strict_types=1);

namespace App\Context\Animal\Domain\ValueObject;

enum LifeStatus: string
{
    case ALIVE    = 'alive';
    case DECEASED = 'deceased';
    case MISSING  = 'missing';
}
