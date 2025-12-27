<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Query\AuthenticateUser;

final readonly class AuthenticateUserQuery
{
    public function __construct(
        public string                $email,
        public string                $plainPassword,
        public AuthenticationContext $context,
    ) {
    }
}

