<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Query\GetUserDetails;

readonly class UserDetails
{
    public function __construct(
        public string $id,
        public string $email,
        public string $createdAt,
        public string $status,
        public ?string $emailVerifiedAt,
    ) {
    }
}
