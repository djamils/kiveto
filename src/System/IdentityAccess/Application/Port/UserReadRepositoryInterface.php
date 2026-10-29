<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Port;

use App\System\IdentityAccess\Application\Query\ListUsers\UserCollection;

interface UserReadRepositoryInterface
{
    public function listAll(
        ?string $search = null,
        ?string $type = null,
        ?string $status = null,
    ): UserCollection;

    /**
     * The status of each known user among these ids.
     *
     * @param list<string> $userIds
     *
     * @return array<string, string> userId → status
     */
    public function statuses(array $userIds): array;
}
