<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Port;

final readonly class UserSessionRecord
{
    public function __construct(
        public string $id,
        public string $userId,
        public \DateTimeImmutable $lastSeenAt,
        public bool $revoked,
    ) {
    }
}
