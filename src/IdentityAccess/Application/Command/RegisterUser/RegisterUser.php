<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\RegisterUser;

readonly class RegisterUser
{
    public function __construct(
        public string $email,
        public string $passwordHash,
    ) {
    }
}
