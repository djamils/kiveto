<?php

declare(strict_types=1);

namespace App\Animal\Domain\Enum;

enum Sex: string
{
    case MALE    = 'male';
    case FEMALE  = 'female';
    case UNKNOWN = 'unknown';
}
