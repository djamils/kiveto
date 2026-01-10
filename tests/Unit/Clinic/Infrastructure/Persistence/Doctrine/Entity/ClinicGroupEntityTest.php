<?php

declare(strict_types=1);

namespace App\Tests\Unit\Clinic\Infrastructure\Persistence\Doctrine\Entity;

use App\Clinic\Domain\ValueObject\ClinicGroupStatus;
use App\Clinic\Infrastructure\Persistence\Doctrine\Entity\ClinicGroupEntity;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ClinicGroupEntityTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $entity = new ClinicGroupEntity();
        $id     = Uuid::v7();
        $createdAt = new \DateTimeImmutable('2024-01-01T10:00:00Z');

        $entity->setId($id);
        $entity->setName('Test Group');
        $entity->setStatus(ClinicGroupStatus::ACTIVE);
        $entity->setCreatedAt($createdAt);

        self::assertSame($id, $entity->getId());
        self::assertSame('Test Group', $entity->getName());
        self::assertSame(ClinicGroupStatus::ACTIVE, $entity->getStatus());
        self::assertSame($createdAt, $entity->getCreatedAt());
    }
}
