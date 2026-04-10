<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\AccessControl\Infrastructure\Persistence\Doctrine\Repository;

use App\Fixtures\System\AccessControl\Factory\RoleAssignmentEntityFactory;
use App\System\AccessControl\Domain\Repository\RoleAssignmentRepositoryInterface;
use App\System\AccessControl\Domain\RoleAssignment;
use App\System\AccessControl\Domain\ValueObject\SubjectId;
use App\System\AccessControl\Domain\ValueObject\TenantId;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class DoctrineRoleAssignmentRepositoryTest extends KernelTestCase
{
    use Factories;

    private const string SUBJECT_ID = '01912345-6789-7abc-8def-000000000001';
    private const string TENANT_ID  = '01912345-6789-7abc-8def-000000000002';

    public function testSaveAndFindBySubjectAndTenant(): void
    {
        $assignment = RoleAssignment::create(
            SubjectId::fromString(self::SUBJECT_ID),
            TenantId::fromString(self::TENANT_ID),
            'VETERINARY',
        );

        $this->repo()->save($assignment);

        $found = $this->repo()->findBySubjectAndTenant(
            SubjectId::fromString(self::SUBJECT_ID),
            TenantId::fromString(self::TENANT_ID),
        );

        self::assertNotNull($found);
        self::assertSame(self::SUBJECT_ID, $found->subjectId()->toString());
        self::assertSame(self::TENANT_ID, $found->tenantId()->toString());
        self::assertSame('VETERINARY', $found->roleKey());
    }

    public function testFindBySubjectAndTenantReturnsNullWhenNotFound(): void
    {
        $found = $this->repo()->findBySubjectAndTenant(
            SubjectId::fromString(self::SUBJECT_ID),
            TenantId::fromString(self::TENANT_ID),
        );

        self::assertNull($found);
    }

    public function testSaveUpdatesExistingAssignment(): void
    {
        RoleAssignmentEntityFactory::new()
            ->withSubjectId(self::SUBJECT_ID)
            ->withTenantId(self::TENANT_ID)
            ->withRoleKey('VETERINARY')
            ->create()
        ;

        $assignment = RoleAssignment::create(
            SubjectId::fromString(self::SUBJECT_ID),
            TenantId::fromString(self::TENANT_ID),
            'MANAGER',
        );

        $this->repo()->save($assignment);

        $found = $this->repo()->findBySubjectAndTenant(
            SubjectId::fromString(self::SUBJECT_ID),
            TenantId::fromString(self::TENANT_ID),
        );

        self::assertNotNull($found);
        self::assertSame('MANAGER', $found->roleKey());
    }

    public function testDeleteBySubjectAndTenant(): void
    {
        RoleAssignmentEntityFactory::new()
            ->withSubjectId(self::SUBJECT_ID)
            ->withTenantId(self::TENANT_ID)
            ->withRoleKey('VETERINARY')
            ->create()
        ;

        $this->repo()->deleteBySubjectAndTenant(
            SubjectId::fromString(self::SUBJECT_ID),
            TenantId::fromString(self::TENANT_ID),
        );

        $found = $this->repo()->findBySubjectAndTenant(
            SubjectId::fromString(self::SUBJECT_ID),
            TenantId::fromString(self::TENANT_ID),
        );

        self::assertNull($found);
    }

    public function testDeleteBySubjectAndTenantDoesNothingWhenNotFound(): void
    {
        $this->expectNotToPerformAssertions();

        $this->repo()->deleteBySubjectAndTenant(
            SubjectId::fromString(self::SUBJECT_ID),
            TenantId::fromString(self::TENANT_ID),
        );
    }

    private function repo(): RoleAssignmentRepositoryInterface
    {
        $repo = self::getContainer()->get(RoleAssignmentRepositoryInterface::class);
        \assert($repo instanceof RoleAssignmentRepositoryInterface);

        return $repo;
    }
}
