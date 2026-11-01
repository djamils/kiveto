<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\DisablePortalUser;

use App\Shared\Application\Bus\CommandInterface;

/**
 * The owner confirmed the deletion of their account: it is disabled and
 * every refresh token revoked, in the caller's transaction. From the commit
 * on, no access token, refresh or sign-in of the account is accepted.
 */
final readonly class DisablePortalUser implements CommandInterface
{
    public function __construct(
        public string $userId,
    ) {
    }
}
