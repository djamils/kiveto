<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Port;

final readonly class PasswordResetRequest
{
    /**
     * @param string $requestedAtLabel « 16/10/2026 à 08:14 » (Europe/Paris)
     */
    public function __construct(
        public string $tokenId,
        public string $userId,
        public string $email,
        public string $resetUrl,
        public string $requestedAtLabel,
    ) {
    }
}
