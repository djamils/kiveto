<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Infrastructure\Persistence\Doctrine\Entity;

use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipStatus;
use App\Context\Clinic\Infrastructure\Persistence\Doctrine\Entity\ClinicMembershipEntity;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ClinicMembershipEntityTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $entity     = new ClinicMembershipEntity();
        $id         = Uuid::v7();
        $clinicId   = Uuid::v7();
        $userId     = Uuid::v7();
        $validFrom  = new \DateTimeImmutable('2026-01-01T00:00:00Z');
        $validUntil = new \DateTimeImmutable('2026-12-31T23:59:59Z');
        $createdAt  = new \DateTimeImmutable('2026-01-01T00:00:00Z');

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

    public function testValidUntilCanBeNull(): void
    {
        $entity = new ClinicMembershipEntity();
        $entity->setValidUntil(null);

        self::assertNull($entity->getValidUntil());
    }
}
