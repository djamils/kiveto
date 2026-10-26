<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Domain\Token;

enum TokenPurpose: string
{
    case INVITATION     = 'INVITATION';
    case PASSWORD_RESET = 'PASSWORD_RESET';

    /** How long a link of this kind may be used. */
    public function lifetime(): \DateInterval
    {
        return match ($this) {
            self::INVITATION     => new \DateInterval('PT72H'),
            self::PASSWORD_RESET => new \DateInterval('PT30M'),
        };
    }
}
