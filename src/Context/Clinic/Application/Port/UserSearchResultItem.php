<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Port;

final readonly class UserSearchResultItem
{
    public function __construct(
        public string $userId,
        public string $email,
    ) {
    }
}
