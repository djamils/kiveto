<?php

declare(strict_types=1);

namespace App\Context\Client\Domain\ValueObject;

enum ConsentActorType: string
{
    case PORTAL_USER = 'portal_user';
    case STAFF       = 'staff';
    case SYSTEM      = 'system';
}
