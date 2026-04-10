<?php

declare(strict_types=1);

namespace App\System\AccessControl\Infrastructure\Persistence\Doctrine\Mapper;

use App\System\AccessControl\Domain\RolePermission;
use App\System\AccessControl\Domain\ValueObject\Permission;
use App\System\AccessControl\Infrastructure\Persistence\Doctrine\Entity\RolePermissionEntity;
use Symfony\Component\Uid\Uuid;

final class RolePermissionMapper
{
    public function toDomain(RolePermissionEntity $entity): RolePermission
    {
        return RolePermission::create(
            roleKey: $entity->getRoleKey(),
            permission: Permission::fromString($entity->getPermission()),
        );
    }

    public function toEntity(RolePermission $rolePermission, Uuid $id): RolePermissionEntity
    {
        $entity = new RolePermissionEntity();
        $entity->setId($id);
        $entity->setRoleKey($rolePermission->roleKey());
        $entity->setPermission($rolePermission->permission()->toString());

        return $entity;
    }
}
