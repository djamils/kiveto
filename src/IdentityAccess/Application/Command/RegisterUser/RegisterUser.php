<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\RegisterUser;

use App\IdentityAccess\Domain\UserType;

readonly class RegisterUser
{
    public function __construct(
        public string $email,
        public string $plainPassword,
        public UserType $type,
    ) {
    }
}
