<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Query\ListUsers;

final readonly class UserListItem
{
    public function __construct(
        public string $id,
        public string $email,
        public string $type,
        public string $status,
        public ?\DateTimeImmutable $emailVerifiedAt,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
