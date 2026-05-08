<?php

declare(strict_types=1);

namespace App\Context\Animal\Domain\ValueObject;

enum RegistryType: string
{
    case NONE             = 'none';
    case LOF              = 'lof';
    case LOOF             = 'loof';
    case OTHER            = 'other';
    case FOREIGN_REGISTRY = 'foreign_registry';
}
