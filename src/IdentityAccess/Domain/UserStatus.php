<?php

declare(strict_types=1);

namespace App\IdentityAccess\Domain;

enum UserStatus: string
{
    case PENDING  = 'PENDING';
    case ACTIVE   = 'ACTIVE';
    case DISABLED = 'DISABLED';
}

