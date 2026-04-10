<?php

declare(strict_types=1);

namespace App\Fixtures\System\AccessControl\Factory;

use App\System\AccessControl\Infrastructure\Persistence\Doctrine\Entity\RolePermissionEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<RolePermissionEntity>
 */
final class RolePermissionEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return RolePermissionEntity::class;
    }

    public function withRoleKey(string $roleKey): self
    {
        return $this->with(['roleKey' => $roleKey]);
    }

    public function withPermission(string $permission): self
    {
        return $this->with(['permission' => $permission]);
    }

    protected function defaults(): array
    {
        return [
            'id'         => Uuid::v7(),
            'roleKey'    => 'VETERINARY',
            'permission' => 'view_dashboard',
        ];
    }
}
