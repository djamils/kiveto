<?php

declare(strict_types=1);

namespace App\Fixtures\System\AccessControl\Factory;

use App\System\AccessControl\Infrastructure\Persistence\Doctrine\Entity\RoleAssignmentEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<RoleAssignmentEntity>
 */
final class RoleAssignmentEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return RoleAssignmentEntity::class;
    }

    public function withSubjectId(string $subjectId): self
    {
        return $this->with(['subjectId' => Uuid::fromString($subjectId)]);
    }

    public function withTenantId(string $tenantId): self
    {
        return $this->with(['tenantId' => Uuid::fromString($tenantId)]);
    }

    public function withRoleKey(string $roleKey): self
    {
        return $this->with(['roleKey' => $roleKey]);
    }

    protected function defaults(): array
    {
        return [
            'id'        => Uuid::v7(),
            'subjectId' => Uuid::v7(),
            'tenantId'  => Uuid::v7(),
            'roleKey'   => 'VETERINARY',
        ];
    }
}
