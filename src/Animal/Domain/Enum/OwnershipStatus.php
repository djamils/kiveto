<?php

declare(strict_types=1);

namespace App\Animal\Domain\Enum;

enum OwnershipStatus: string
{
    case ACTIVE = 'active';
    case ENDED  = 'ended';
}
