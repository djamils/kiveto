<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\RevokePortalSession;

use App\Shared\Application\Bus\CommandInterface;

/**
 * « Se déconnecter »: revokes the family of the refresh token, when it
 * belongs to `userId` (the caller); otherwise nothing happens.
 */
final readonly class RevokePortalSession implements CommandInterface
{
    public function __construct(
        #[\SensitiveParameter]
        public string $refreshToken,
        public string $userId,
    ) {
    }
}
