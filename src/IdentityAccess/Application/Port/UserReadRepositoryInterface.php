<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Port;

use App\IdentityAccess\Application\Query\ListUsers\UserCollection;

interface UserReadRepositoryInterface
{
    public function listAll(
        ?string $search = null,
        ?string $type = null,
        ?string $status = null,
    ): UserCollection;
}
