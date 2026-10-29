<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\RefreshPortalSession;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Exchanges a refresh token for a new pair. Answers a RefreshResult and
 * never throws on a bad token: a family revocation must be committed.
 */
final readonly class RefreshPortalSession implements CommandInterface
{
    public function __construct(
        #[\SensitiveParameter]
        public string $refreshToken,
    ) {
    }
}
