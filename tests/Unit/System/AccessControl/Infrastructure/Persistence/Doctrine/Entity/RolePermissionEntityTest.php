<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\AccessControl\Infrastructure\Persistence\Doctrine\Entity;

use App\System\AccessControl\Infrastructure\Persistence\Doctrine\Entity\RolePermissionEntity;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class RolePermissionEntityTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $entity = new RolePermissionEntity();
        $id     = Uuid::v7();

        $entity->setId($id);
        $entity->setRoleKey('MANAGER');
        $entity->setPermission('manage_staff');

        self::assertSame($id, $entity->getId());
        self::assertSame('MANAGER', $entity->getRoleKey());
        self::assertSame('manage_staff', $entity->getPermission());
    }
}
