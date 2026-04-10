<?php

declare(strict_types=1);

namespace App\System\AccessControl\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_role_permission', columns: ['role_key', 'permission'])]
#[ORM\Index(name: 'idx_role_key', columns: ['role_key'])]
class RolePermissionEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'role_key', type: 'string', length: 60)]
    private string $roleKey;

    #[ORM\Column(type: 'string', length: 100)]
    private string $permission;

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function setId(Uuid $id): void
    {
        $this->id = $id;
    }

    public function getRoleKey(): string
    {
        return $this->roleKey;
    }

    public function setRoleKey(string $roleKey): void
    {
        $this->roleKey = $roleKey;
    }

    public function getPermission(): string
    {
        return $this->permission;
    }

    public function setPermission(string $permission): void
    {
        $this->permission = $permission;
    }
}
