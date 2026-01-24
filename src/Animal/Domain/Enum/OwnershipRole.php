<?php

declare(strict_types=1);

namespace App\Animal\Domain\Enum;

enum OwnershipRole: string
{
    case PRIMARY   = 'primary';
    case SECONDARY = 'secondary';
}
