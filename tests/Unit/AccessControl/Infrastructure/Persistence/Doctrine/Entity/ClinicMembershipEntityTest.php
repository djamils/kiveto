<?php

declare(strict_types=1);

namespace App\Tests\Unit\AccessControl\Infrastructure\Persistence\Doctrine\Entity;

use App\AccessControl\Domain\ValueObject\ClinicMemberRole;
use App\AccessControl\Domain\ValueObject\ClinicMembershipEngagement;
use App\AccessControl\Domain\ValueObject\ClinicMembershipStatus;
use App\AccessControl\Infrastructure\Persistence\Doctrine\Entity\ClinicMembershipEntity;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ClinicMembershipEntityTest extends TestCase
{
    public function testEntityGettersAndSetters(): void
    {
        $entity = new ClinicMembershipEntity();

        $id         = Uuid::v7();
        $clinicId   = Uuid::v7();
        $userId     = Uuid::v7();
        $validFrom  = new \DateTimeImmutable('2024-01-01');
        $validUntil = new \DateTimeImmutable('2025-01-01');
        $createdAt  = new \DateTimeImmutable('2024-01-01');

        $entity->setId($id);
        $entity->setClinicId($clinicId);
        $entity->setUserId($userId);
        $entity->setRole(ClinicMemberRole::VETERINARY);
        $entity->setEngagement(ClinicMembershipEngagement::EMPLOYEE);
        $entity->setStatus(ClinicMembershipStatus::ACTIVE);
        $entity->setValidFrom($validFrom);
        $entity->setValidUntil($validUntil);
        $entity->setCreatedAt($createdAt);

        self::assertSame($id, $entity->getId());
        self::assertSame($clinicId, $entity->getClinicId());
        self::assertSame($userId, $entity->getUserId());
        self::assertSame(ClinicMemberRole::VETERINARY, $entity->getRole());
        self::assertSame(ClinicMembershipEngagement::EMPLOYEE, $entity->getEngagement());
        self::assertSame(ClinicMembershipStatus::ACTIVE, $entity->getStatus());
        self::assertSame($validFrom, $entity->getValidFrom());
        self::assertSame($validUntil, $entity->getValidUntil());
        self::assertSame($createdAt, $entity->getCreatedAt());
    }

    public function testEntityWithNullValidUntil(): void
    {
        $entity = new ClinicMembershipEntity();

        $entity->setValidFrom(new \DateTimeImmutable('2024-01-01'));
        $entity->setValidUntil(null);

        self::assertNull($entity->getValidUntil());
    }

    public function testEntityWithAllRoles(): void
    {
        $entity = new ClinicMembershipEntity();

        $entity->setRole(ClinicMemberRole::CLINIC_ADMIN);
        self::assertSame(ClinicMemberRole::CLINIC_ADMIN, $entity->getRole());

        $entity->setRole(ClinicMemberRole::VETERINARY);
        self::assertSame(ClinicMemberRole::VETERINARY, $entity->getRole());

        $entity->setRole(ClinicMemberRole::ASSISTANT_VETERINARY);
        self::assertSame(ClinicMemberRole::ASSISTANT_VETERINARY, $entity->getRole());
    }

    public function testEntityWithAllEngagements(): void
    {
        $entity = new ClinicMembershipEntity();

        $entity->setEngagement(ClinicMembershipEngagement::EMPLOYEE);
        self::assertSame(ClinicMembershipEngagement::EMPLOYEE, $entity->getEngagement());

        $entity->setEngagement(ClinicMembershipEngagement::CONTRACTOR);
        self::assertSame(ClinicMembershipEngagement::CONTRACTOR, $entity->getEngagement());
    }

    public function testEntityWithAllStatuses(): void
    {
        $entity = new ClinicMembershipEntity();

        $entity->setStatus(ClinicMembershipStatus::ACTIVE);
        self::assertSame(ClinicMembershipStatus::ACTIVE, $entity->getStatus());

        $entity->setStatus(ClinicMembershipStatus::DISABLED);
        self::assertSame(ClinicMembershipStatus::DISABLED, $entity->getStatus());
    }
}
