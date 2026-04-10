<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\AccessControl\Infrastructure\Persistence\Doctrine\Entity;

use App\System\AccessControl\Infrastructure\Persistence\Doctrine\Entity\RoleAssignmentEntity;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class RoleAssignmentEntityTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $entity    = new RoleAssignmentEntity();
        $id        = Uuid::v7();
        $subjectId = Uuid::v7();
        $tenantId  = Uuid::v7();

        $entity->setId($id);
        $entity->setSubjectId($subjectId);
        $entity->setTenantId($tenantId);
        $entity->setRoleKey('VETERINARY');

        self::assertSame($id, $entity->getId());
        self::assertSame($subjectId, $entity->getSubjectId());
        self::assertSame($tenantId, $entity->getTenantId());
        self::assertSame('VETERINARY', $entity->getRoleKey());
    }
}
