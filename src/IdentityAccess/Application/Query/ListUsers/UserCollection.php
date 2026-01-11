<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Query\ListUsers;

final readonly class UserCollection
{
    /**
     * @param list<UserListItem> $users
     */
    public function __construct(
        public array $users,
        public int $total,
    ) {
    }
}
