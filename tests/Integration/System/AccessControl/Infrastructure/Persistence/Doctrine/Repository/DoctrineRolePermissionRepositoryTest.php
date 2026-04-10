<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\AccessControl\Infrastructure\Persistence\Doctrine\Repository;

use App\Fixtures\System\AccessControl\Factory\RolePermissionEntityFactory;
use App\System\AccessControl\Domain\ValueObject\Permission;
use App\System\AccessControl\Infrastructure\Persistence\Doctrine\Repository\DoctrineRolePermissionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class DoctrineRolePermissionRepositoryTest extends KernelTestCase
{
    use Factories;

    public function testSaveAllAndFindByRoleKey(): void
    {
        $this->repo()->saveAll(
            'VETERINARY',
            Permission::fromString('create_prescription'),
            Permission::fromString('view_medical_record'),
        );

        $permissions = $this->repo()->findByRoleKey('VETERINARY');

        self::assertCount(2, $permissions);

        $values = array_map(static fn (Permission $p): string => $p->toString(), $permissions);
        sort($values);

        self::assertSame(['create_prescription', 'view_medical_record'], $values);
    }

    public function testSaveAllSkipsDuplicates(): void
    {
        $this->repo()->saveAll(
            'MANAGER',
            Permission::fromString('manage_staff'),
        );

        // Save again with same + new permission
        $this->repo()->saveAll(
            'MANAGER',
            Permission::fromString('manage_staff'),
            Permission::fromString('manage_billing'),
        );

        $permissions = $this->repo()->findByRoleKey('MANAGER');

        self::assertCount(2, $permissions);
    }

    public function testFindByRoleKeyReturnsEmptyArrayWhenNoneFound(): void
    {
        $permissions = $this->repo()->findByRoleKey('NONEXISTENT');

        self::assertSame([], $permissions);
    }

    public function testDeleteAllRemovesEverything(): void
    {
        RolePermissionEntityFactory::new()
            ->withRoleKey('VETERINARY')
            ->withPermission('create_prescription')
            ->create()
        ;

        RolePermissionEntityFactory::new()
            ->withRoleKey('MANAGER')
            ->withPermission('manage_staff')
            ->create()
        ;

        $this->repo()->deleteAll();

        self::assertSame([], $this->repo()->findByRoleKey('VETERINARY'));
        self::assertSame([], $this->repo()->findByRoleKey('MANAGER'));
    }

    private function repo(): DoctrineRolePermissionRepository
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);

        return new DoctrineRolePermissionRepository($em);
    }
}
