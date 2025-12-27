<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Query\AuthenticateUser;

use App\IdentityAccess\Domain\ValueObject\UserType;

enum AuthenticationContext: string
{
    case CLINIC     = 'CLINIC';
    case PORTAL     = 'PORTAL';
    case BACKOFFICE = 'BACKOFFICE';

    public function allowedUserType(): UserType
    {
        return match ($this) {
            self::CLINIC     => UserType::CLINIC,
            self::PORTAL     => UserType::PORTAL,
            self::BACKOFFICE => UserType::BACKOFFICE,
        };
    }
}

