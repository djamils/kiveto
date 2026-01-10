<?php

declare(strict_types=1);

namespace App\Tests\Unit\Clinic\Infrastructure\Persistence\Doctrine\Entity;

use App\Clinic\Domain\ValueObject\ClinicStatus;
use App\Clinic\Infrastructure\Persistence\Doctrine\Entity\ClinicEntity;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ClinicEntityTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $entity = new ClinicEntity();
        $id     = Uuid::v7();
        $groupId = Uuid::v7();
        $createdAt = new \DateTimeImmutable('2024-01-01T10:00:00Z');
        $updatedAt = new \DateTimeImmutable('2024-01-02T10:00:00Z');

        $entity->setId($id);
        $entity->setName('Test Clinic');
        $entity->setSlug('test-clinic');
        $entity->setTimeZone('Europe/Paris');
        $entity->setLocale('fr-FR');
        $entity->setStatus(ClinicStatus::ACTIVE);
        $entity->setClinicGroupId($groupId);
        $entity->setCreatedAt($createdAt);
        $entity->setUpdatedAt($updatedAt);

        self::assertSame($id, $entity->getId());
        self::assertSame('Test Clinic', $entity->getName());
        self::assertSame('test-clinic', $entity->getSlug());
        self::assertSame('Europe/Paris', $entity->getTimeZone());
        self::assertSame('fr-FR', $entity->getLocale());
        self::assertSame(ClinicStatus::ACTIVE, $entity->getStatus());
        self::assertSame($groupId, $entity->getClinicGroupId());
        self::assertSame($createdAt, $entity->getCreatedAt());
        self::assertSame($updatedAt, $entity->getUpdatedAt());
    }

    public function testClinicGroupIdCanBeNull(): void
    {
        $entity = new ClinicEntity();
        $entity->setClinicGroupId(null);

        self::assertNull($entity->getClinicGroupId());
    }
}
