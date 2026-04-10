<?php

declare(strict_types=1);

namespace App\System\AccessControl\Domain;

use App\System\AccessControl\Domain\ValueObject\SubjectId;
use App\System\AccessControl\Domain\ValueObject\TenantId;

final class RoleAssignment
{
    private function __construct(
        private SubjectId $subjectId,
        private TenantId $tenantId,
        private string $roleKey,
    ) {
    }

    public static function create(SubjectId $subjectId, TenantId $tenantId, string $roleKey): self
    {
        return new self($subjectId, $tenantId, $roleKey);
    }

    public function subjectId(): SubjectId
    {
        return $this->subjectId;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function roleKey(): string
    {
        return $this->roleKey;
    }

    public function changeRoleKey(string $newRoleKey): void
    {
        $this->roleKey = $newRoleKey;
    }
}
