<?php

declare(strict_types=1);

namespace App\System\AccessControl\Infrastructure\Persistence\Doctrine\Mapper;

use App\System\AccessControl\Domain\RoleAssignment;
use App\System\AccessControl\Domain\ValueObject\SubjectId;
use App\System\AccessControl\Domain\ValueObject\TenantId;
use App\System\AccessControl\Infrastructure\Persistence\Doctrine\Entity\RoleAssignmentEntity;
use Symfony\Component\Uid\Uuid;

final class RoleAssignmentMapper
{
    public function toDomain(RoleAssignmentEntity $entity): RoleAssignment
    {
        return RoleAssignment::create(
            subjectId: SubjectId::fromString($entity->getSubjectId()->toRfc4122()),
            tenantId: TenantId::fromString($entity->getTenantId()->toRfc4122()),
            roleKey: $entity->getRoleKey(),
        );
    }

    public function toEntity(RoleAssignment $assignment, Uuid $id): RoleAssignmentEntity
    {
        $entity = new RoleAssignmentEntity();
        $entity->setId($id);
        $entity->setSubjectId(Uuid::fromString($assignment->subjectId()->toString()));
        $entity->setTenantId(Uuid::fromString($assignment->tenantId()->toString()));
        $entity->setRoleKey($assignment->roleKey());

        return $entity;
    }
}
