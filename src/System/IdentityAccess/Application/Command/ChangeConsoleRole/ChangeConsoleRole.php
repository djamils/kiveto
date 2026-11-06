<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\ChangeConsoleRole;

use App\Shared\Application\Bus\CommandInterface;

/**
 * « Équipe · Rôle »: administrator or support. The console always keeps an
 * administrator who can sign in. The handler returns whether it changed.
 */
final readonly class ChangeConsoleRole implements CommandInterface
{
    public function __construct(
        public string $userId,
        public string $role,
    ) {
    }
}
