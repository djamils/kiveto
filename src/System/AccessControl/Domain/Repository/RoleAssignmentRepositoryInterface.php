<?php

declare(strict_types=1);

namespace App\System\AccessControl\Domain\Repository;

use App\System\AccessControl\Domain\RoleAssignment;
use App\System\AccessControl\Domain\ValueObject\SubjectId;
use App\System\AccessControl\Domain\ValueObject\TenantId;

interface RoleAssignmentRepositoryInterface
{
    public function save(RoleAssignment $assignment): void;

    public function findBySubjectAndTenant(SubjectId $subjectId, TenantId $tenantId): ?RoleAssignment;

    public function deleteBySubjectAndTenant(SubjectId $subjectId, TenantId $tenantId): void;
}
