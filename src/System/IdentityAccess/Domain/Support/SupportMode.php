<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Domain\Support;

enum SupportMode: string
{
    case READ = 'read';

    case WRITE = 'write';

    public function label(): string
    {
        return match ($this) {
            self::READ  => 'Lecture seule',
            self::WRITE => 'Lecture et écriture',
        };
    }
}
