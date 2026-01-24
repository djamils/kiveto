<?php

declare(strict_types=1);

namespace App\Animal\Domain\Enum;

enum ReproductiveStatus: string
{
    case INTACT   = 'intact';
    case NEUTERED = 'neutered';
    case UNKNOWN  = 'unknown';
}
