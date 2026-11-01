<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Port;

/** The code sent to the address an owner wants on their account. */
final readonly class PortalEmailVerificationRequest
{
    public function __construct(
        public string $challengeId,
        public string $userId,
        public string $email,
        public string $code,
        public int $validityMinutes,
    ) {
    }
}
