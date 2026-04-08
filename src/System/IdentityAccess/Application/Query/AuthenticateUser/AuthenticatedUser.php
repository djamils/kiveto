<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Query\AuthenticateUser;

use App\System\IdentityAccess\Domain\ValueObject\UserType;

final readonly class AuthenticatedUser
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        public string $id,
        public string $email,
        public UserType $type,
        public array $roles = ['ROLE_USER'],
    ) {
    }
}
