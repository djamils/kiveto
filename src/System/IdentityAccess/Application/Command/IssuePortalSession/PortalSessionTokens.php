<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\IssuePortalSession;

/** A token pair of the Portal API. The refresh secret only exists here. */
final readonly class PortalSessionTokens
{
    public function __construct(
        public string $accessToken,
        public \DateTimeImmutable $accessTokenExpiresAt,
        public string $refreshToken,
        public \DateTimeImmutable $refreshTokenExpiresAt,
    ) {
    }
}
