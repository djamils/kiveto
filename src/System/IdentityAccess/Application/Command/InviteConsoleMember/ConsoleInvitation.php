<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\InviteConsoleMember;

/**
 * The link a new member of the Kiveto team opens to choose a password,
 * shown once to whoever invited them.
 */
final readonly class ConsoleInvitation
{
    public function __construct(
        public string $userId,
        public string $email,
        public string $url,
        public \DateTimeImmutable $expiresAt,
    ) {
    }
}
