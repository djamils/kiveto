<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Query\ListUserSessions;

final readonly class UserSessionView
{
    public function __construct(
        public string $id,
        public ?string $userAgent,
        public ?string $ipAddress,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $lastSeenAt,
        public bool $current,
    ) {
    }
}
