<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Query\AuthenticateUser;

use App\IdentityAccess\Domain\UserType;

enum LoginContext: string
{
    case CLINIC     = 'CLINIC';
    case PORTAL     = 'PORTAL';
    case BACKOFFICE = 'BACKOFFICE';

    public function toUserType(): UserType
    {
        return match ($this) {
            self::CLINIC     => UserType::CLINIC,
            self::PORTAL     => UserType::PORTAL,
            self::BACKOFFICE => UserType::BACKOFFICE,
        };
    }
}

