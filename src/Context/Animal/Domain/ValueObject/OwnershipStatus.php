<?php

declare(strict_types=1);

namespace App\Context\Animal\Domain\ValueObject;

enum OwnershipStatus: string
{
    case ACTIVE = 'active';
    case ENDED  = 'ended';
}
