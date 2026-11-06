<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\InviteConsoleMember;

use App\Shared\Application\Bus\CommandInterface;

/**
 * « Équipe · Inviter » (and the first administrator, on the command line):
 * a console account for this address, PENDING with its name and role,
 * and a 7-day link to choose its password; the second factor is set up at
 * its first sign-in. Inviting again an address still invited issues a new
 * link (the previous one dies). The handler returns a ConsoleInvitation.
 */
final readonly class InviteConsoleMember implements CommandInterface
{
    /**
     * @param string $role 'admin' or 'support'
     */
    public function __construct(
        public string $email,
        public string $displayName,
        public string $role,
    ) {
    }
}
