<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Domain\ValueObject;

/**
 * What a member of the Kiveto team may do in the console: everything
 * (administrator), or the clinics, their users and the support sessions,
 * the rest in reading (support).
 */
enum ConsoleRole: string
{
    case ADMIN = 'admin';

    case SUPPORT = 'support';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN   => 'Administrateur',
            self::SUPPORT => 'Support',
        };
    }

    /** The Symfony role of a session of this member. */
    public function securityRole(): string
    {
        return match ($this) {
            self::ADMIN   => 'ROLE_CONSOLE_ADMIN',
            self::SUPPORT => 'ROLE_CONSOLE_SUPPORT',
        };
    }
}
