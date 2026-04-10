<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\AccessControl\Domain;

use App\System\AccessControl\Domain\RoleAssignment;
use App\System\AccessControl\Domain\ValueObject\SubjectId;
use App\System\AccessControl\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class RoleAssignmentTest extends TestCase
{
    public function testCreateAndGetters(): void
    {
        $subjectId = SubjectId::fromString('01912345-6789-7abc-8def-0123456789ab');
        $tenantId  = TenantId::fromString('01912345-6789-7abc-8def-0123456789ac');

        $assignment = RoleAssignment::create($subjectId, $tenantId, 'VETERINARY');

        self::assertTrue($assignment->subjectId()->equals($subjectId));
        self::assertTrue($assignment->tenantId()->equals($tenantId));
        self::assertSame('VETERINARY', $assignment->roleKey());
    }

    public function testChangeRoleKey(): void
    {
        $assignment = RoleAssignment::create(
            SubjectId::fromString('01912345-6789-7abc-8def-0123456789ab'),
            TenantId::fromString('01912345-6789-7abc-8def-0123456789ac'),
            'VETERINARY',
        );

        $assignment->changeRoleKey('MANAGER');

        self::assertSame('MANAGER', $assignment->roleKey());
    }
}
