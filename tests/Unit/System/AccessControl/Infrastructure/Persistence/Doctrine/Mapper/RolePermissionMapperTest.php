<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\AccessControl\Infrastructure\Persistence\Doctrine\Mapper;

use App\System\AccessControl\Domain\RolePermission;
use App\System\AccessControl\Domain\ValueObject\Permission;
use App\System\AccessControl\Infrastructure\Persistence\Doctrine\Entity\RolePermissionEntity;
use App\System\AccessControl\Infrastructure\Persistence\Doctrine\Mapper\RolePermissionMapper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class RolePermissionMapperTest extends TestCase
{
    private RolePermissionMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new RolePermissionMapper();
    }

    public function testToDomain(): void
    {
        $entity = new RolePermissionEntity();
        $entity->setId(Uuid::v7());
        $entity->setRoleKey('VETERINARY');
        $entity->setPermission('create_prescription');

        $domain = $this->mapper->toDomain($entity);

        self::assertSame('VETERINARY', $domain->roleKey());
        self::assertSame('create_prescription', $domain->permission()->toString());
    }

    public function testToEntity(): void
    {
        $rp = RolePermission::create('MANAGER', Permission::fromString('manage_staff'));

        $id     = Uuid::v7();
        $entity = $this->mapper->toEntity($rp, $id);

        self::assertSame($id, $entity->getId());
        self::assertSame('MANAGER', $entity->getRoleKey());
        self::assertSame('manage_staff', $entity->getPermission());
    }
}
