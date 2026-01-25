<?php

declare(strict_types=1);

namespace App\Animal\Domain\ValueObject;

enum RegistryType: string
{
    case NONE  = 'none';
    case LOF   = 'lof';
    case LOOF  = 'loof';
    case OTHER = 'other';
}
