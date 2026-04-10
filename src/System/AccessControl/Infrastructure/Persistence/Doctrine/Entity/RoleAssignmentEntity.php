<?php

declare(strict_types=1);

namespace App\System\AccessControl\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_subject_tenant', columns: ['subject_id', 'tenant_id'])]
class RoleAssignmentEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'subject_id', type: UuidType::NAME)]
    private Uuid $subjectId;

    #[ORM\Column(name: 'tenant_id', type: UuidType::NAME)]
    private Uuid $tenantId;

    #[ORM\Column(name: 'role_key', type: 'string', length: 60)]
    private string $roleKey;

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function setId(Uuid $id): void
    {
        $this->id = $id;
    }

    public function getSubjectId(): Uuid
    {
        return $this->subjectId;
    }

    public function setSubjectId(Uuid $subjectId): void
    {
        $this->subjectId = $subjectId;
    }

    public function getTenantId(): Uuid
    {
        return $this->tenantId;
    }

    public function setTenantId(Uuid $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getRoleKey(): string
    {
        return $this->roleKey;
    }

    public function setRoleKey(string $roleKey): void
    {
        $this->roleKey = $roleKey;
    }
}
