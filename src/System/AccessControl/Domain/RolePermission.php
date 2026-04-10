<?php

declare(strict_types=1);

namespace App\System\AccessControl\Domain;

use App\System\AccessControl\Domain\ValueObject\Permission;

final class RolePermission
{
    private function __construct(
        private string $roleKey,
        private Permission $permission,
    ) {
    }

    public static function create(string $roleKey, Permission $permission): self
    {
        return new self($roleKey, $permission);
    }

    public function roleKey(): string
    {
        return $this->roleKey;
    }

    public function permission(): Permission
    {
        return $this->permission;
    }
}
