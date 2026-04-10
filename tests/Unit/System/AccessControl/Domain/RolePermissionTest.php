<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\AccessControl\Domain;

use App\System\AccessControl\Domain\RolePermission;
use App\System\AccessControl\Domain\ValueObject\Permission;
use PHPUnit\Framework\TestCase;

final class RolePermissionTest extends TestCase
{
    public function testCreateAndGetters(): void
    {
        $permission = Permission::fromString('create_prescription');

        $rp = RolePermission::create('VETERINARY', $permission);

        self::assertSame('VETERINARY', $rp->roleKey());
        self::assertTrue($rp->permission()->equals($permission));
    }
}
