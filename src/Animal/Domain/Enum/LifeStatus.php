<?php

declare(strict_types=1);

namespace App\Animal\Domain\Enum;

enum LifeStatus: string
{
    case ALIVE    = 'alive';
    case DECEASED = 'deceased';
    case MISSING  = 'missing';
}
