<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\AccessControl\Infrastructure\Persistence\Doctrine\Mapper;

use App\System\AccessControl\Domain\RoleAssignment;
use App\System\AccessControl\Domain\ValueObject\SubjectId;
use App\System\AccessControl\Domain\ValueObject\TenantId;
use App\System\AccessControl\Infrastructure\Persistence\Doctrine\Entity\RoleAssignmentEntity;
use App\System\AccessControl\Infrastructure\Persistence\Doctrine\Mapper\RoleAssignmentMapper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class RoleAssignmentMapperTest extends TestCase
{
    private RoleAssignmentMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new RoleAssignmentMapper();
    }

    public function testToDomain(): void
    {
        $entity = new RoleAssignmentEntity();
        $entity->setId(Uuid::fromString('01912345-6789-7abc-8def-0123456789ab'));
        $entity->setSubjectId(Uuid::fromString('01912345-6789-7abc-8def-0123456789ac'));
        $entity->setTenantId(Uuid::fromString('01912345-6789-7abc-8def-0123456789ad'));
        $entity->setRoleKey('VETERINARY');

        $domain = $this->mapper->toDomain($entity);

        self::assertSame('01912345-6789-7abc-8def-0123456789ac', $domain->subjectId()->toString());
        self::assertSame('01912345-6789-7abc-8def-0123456789ad', $domain->tenantId()->toString());
        self::assertSame('VETERINARY', $domain->roleKey());
    }

    public function testToEntity(): void
    {
        $assignment = RoleAssignment::create(
            SubjectId::fromString('01912345-6789-7abc-8def-0123456789ac'),
            TenantId::fromString('01912345-6789-7abc-8def-0123456789ad'),
            'MANAGER',
        );

        $id     = Uuid::v7();
        $entity = $this->mapper->toEntity($assignment, $id);

        self::assertSame($id, $entity->getId());
        self::assertSame('01912345-6789-7abc-8def-0123456789ac', $entity->getSubjectId()->toRfc4122());
        self::assertSame('01912345-6789-7abc-8def-0123456789ad', $entity->getTenantId()->toRfc4122());
        self::assertSame('MANAGER', $entity->getRoleKey());
    }
}
