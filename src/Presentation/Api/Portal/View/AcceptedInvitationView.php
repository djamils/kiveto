<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\View;

use ApiPlatform\Metadata\ApiProperty;

/** A new token pair and the account it opens: the app keeps the most recent pair. */
final readonly class AcceptedInvitationView
{
    public function __construct(
        public string $accessToken,
        #[ApiProperty(schema: ['type' => 'string', 'format' => 'date-time'])]
        public string $accessTokenExpiresAt,
        public string $refreshToken,
        #[ApiProperty(schema: ['type' => 'string', 'format' => 'date-time'])]
        public string $refreshTokenExpiresAt,
        public AccountView $account,
    ) {
    }
}
