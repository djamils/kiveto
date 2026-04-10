<?php

declare(strict_types=1);

namespace App\System\AccessControl\Domain\Repository;

use App\System\AccessControl\Domain\ValueObject\Permission;

interface RolePermissionRepositoryInterface
{
    public function saveAll(string $roleKey, Permission ...$permissions): void;

    /**
     * @return list<Permission>
     */
    public function findByRoleKey(string $roleKey): array;

    public function deleteAll(): void;
}
