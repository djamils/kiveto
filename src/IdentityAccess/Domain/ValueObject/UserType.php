<?php

declare(strict_types=1);

namespace App\IdentityAccess\Domain\ValueObject;

enum UserType: string
{
    case CLINIC     = 'CLINIC';
    case PORTAL     = 'PORTAL';
    case BACKOFFICE = 'BACKOFFICE';
}

