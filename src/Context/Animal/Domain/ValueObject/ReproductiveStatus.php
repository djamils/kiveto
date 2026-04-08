<?php

declare(strict_types=1);

namespace App\Context\Animal\Domain\ValueObject;

enum ReproductiveStatus: string
{
    case INTACT   = 'intact';
    case NEUTERED = 'neutered';
    case UNKNOWN  = 'unknown';
}
