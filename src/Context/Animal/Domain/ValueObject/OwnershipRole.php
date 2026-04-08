<?php

declare(strict_types=1);

namespace App\Context\Animal\Domain\ValueObject;

enum OwnershipRole: string
{
    case PRIMARY   = 'primary';
    case SECONDARY = 'secondary';
}
