<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\SwitchConsoleMember;

use App\Shared\Application\Bus\CommandInterface;

/**
 * « Équipe · Désactiver / Réactiver » a member of the Kiveto team. Off: no
 * sign-in any more, every session closed. The console keeps an active
 * administrator, and one never switches oneself off. The handler returns
 * how many sessions were closed.
 */
final readonly class SwitchConsoleMember implements CommandInterface
{
    public function __construct(
        public string $userId,
        public bool $enabled,
        public string $actorId,
    ) {
    }
}
