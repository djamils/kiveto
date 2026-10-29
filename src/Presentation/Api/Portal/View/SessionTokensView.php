<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\View;

use ApiPlatform\Metadata\ApiProperty;
use App\Presentation\Api\Portal\Support\ApiDate;
use App\System\IdentityAccess\Application\Command\IssuePortalSession\PortalSessionTokens;

/** A token pair: the app keeps the most recent one. */
final readonly class SessionTokensView
{
    public function __construct(
        public string $accessToken,
        #[ApiProperty(schema: ['type' => 'string', 'format' => 'date-time'])]
        public string $accessTokenExpiresAt,
        public string $refreshToken,
        #[ApiProperty(schema: ['type' => 'string', 'format' => 'date-time'])]
        public string $refreshTokenExpiresAt,
    ) {
    }

    public static function of(PortalSessionTokens $tokens): self
    {
        return new self($tokens->accessToken, ApiDate::format($tokens->accessTokenExpiresAt), $tokens->refreshToken, ApiDate::format($tokens->refreshTokenExpiresAt));
    }
}
