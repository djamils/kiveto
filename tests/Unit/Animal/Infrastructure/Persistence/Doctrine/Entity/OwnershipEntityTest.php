<?php

declare(strict_types=1);

namespace App\Tests\Unit\Animal\Infrastructure\Persistence\Doctrine\Entity;

use App\Animal\Domain\ValueObject\OwnershipRole;
use App\Animal\Domain\ValueObject\OwnershipStatus;
use App\Animal\Infrastructure\Persistence\Doctrine\Entity\AnimalEntity;
use App\Animal\Infrastructure\Persistence\Doctrine\Entity\OwnershipEntity;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class OwnershipEntityTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $id        = Uuid::v7();
        $clientId  = Uuid::v7();
        $animal    = new AnimalEntity();
        $startedAt = new \DateTimeImmutable('2024-01-01T10:00:00+00:00');
        $endedAt   = new \DateTimeImmutable('2024-06-01T10:00:00+00:00');

        $entity = new OwnershipEntity();
        $entity->setId($id);
        $entity->setAnimal($animal);
        $entity->setClientId($clientId);
        $entity->setRole(OwnershipRole::PRIMARY);
        $entity->setStatus(OwnershipStatus::ACTIVE);
        $entity->setStartedAt($startedAt);
        $entity->setEndedAt($endedAt);

        self::assertSame($id, $entity->getId());
        self::assertSame($animal, $entity->getAnimal());
        self::assertSame($clientId, $entity->getClientId());
        self::assertSame(OwnershipRole::PRIMARY, $entity->getRole());
        self::assertSame(OwnershipStatus::ACTIVE, $entity->getStatus());
        self::assertSame($startedAt, $entity->getStartedAt());
        self::assertSame($endedAt, $entity->getEndedAt());
    }

    public function testSetAnimalToNull(): void
    {
        $entity = new OwnershipEntity();
        $entity->setAnimal(null);

        self::assertNull($entity->getAnimal());
    }

    public function testSecondaryOwnership(): void
    {
        $entity = new OwnershipEntity();
        $entity->setRole(OwnershipRole::SECONDARY);
        $entity->setStatus(OwnershipStatus::ENDED);

        self::assertSame(OwnershipRole::SECONDARY, $entity->getRole());
        self::assertSame(OwnershipStatus::ENDED, $entity->getStatus());
    }
}
