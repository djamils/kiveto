<?php

declare(strict_types=1);

namespace App\Animal\Domain\ValueObject;

enum Sex: string
{
    case MALE    = 'male';
    case FEMALE  = 'female';
    case UNKNOWN = 'unknown';
}
