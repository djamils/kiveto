<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Domain\Token;

enum TokenPurpose: string
{
    case INVITATION         = 'INVITATION';
    case PASSWORD_RESET     = 'PASSWORD_RESET';
    case CONSOLE_INVITATION = 'CONSOLE_INVITATION';

    /** How long a link of this kind may be used. */
    public function lifetime(): \DateInterval
    {
        return match ($this) {
            self::INVITATION, self::CONSOLE_INVITATION => new \DateInterval('P7D'),
            self::PASSWORD_RESET => new \DateInterval('PT30M'),
        };
    }

    /** Whether it lets a PENDING account choose its first password (a clinic's, or the console's). */
    public function isInvitation(): bool
    {
        return self::PASSWORD_RESET !== $this;
    }
}
