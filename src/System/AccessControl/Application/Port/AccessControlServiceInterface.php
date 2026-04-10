<?php

declare(strict_types=1);

namespace App\System\AccessControl\Application\Port;

use App\System\AccessControl\Domain\ValueObject\Permission;
use App\System\AccessControl\Domain\ValueObject\SubjectId;
use App\System\AccessControl\Domain\ValueObject\TenantId;

interface AccessControlServiceInterface
{
    public function isGranted(SubjectId $subject, TenantId $tenant, Permission $permission): bool;
}
