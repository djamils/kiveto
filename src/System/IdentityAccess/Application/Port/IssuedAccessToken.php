<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Port;

final readonly class IssuedAccessToken
{
    public function __construct(
        public string $token,
        public \DateTimeImmutable $expiresAt,
    ) {
    }
}
