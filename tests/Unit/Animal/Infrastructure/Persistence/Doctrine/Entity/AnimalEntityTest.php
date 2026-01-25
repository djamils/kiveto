<?php

declare(strict_types=1);

namespace App\Tests\Unit\Animal\Infrastructure\Persistence\Doctrine\Entity;

use App\Animal\Domain\ValueObject\AnimalStatus;
use App\Animal\Domain\ValueObject\LifeStatus;
use App\Animal\Domain\ValueObject\RegistryType;
use App\Animal\Domain\ValueObject\ReproductiveStatus;
use App\Animal\Domain\ValueObject\Sex;
use App\Animal\Domain\ValueObject\Species;
use App\Animal\Domain\ValueObject\TransferStatus;
use App\Animal\Infrastructure\Persistence\Doctrine\Entity\AnimalEntity;
use App\Animal\Infrastructure\Persistence\Doctrine\Entity\OwnershipEntity;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class AnimalEntityTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $id        = Uuid::v7();
        $clinicId  = Uuid::v7();
        $birthDate = new \DateTimeImmutable('2020-01-01');
        $createdAt = new \DateTimeImmutable('2024-01-01T10:00:00+00:00');
        $updatedAt = new \DateTimeImmutable('2024-06-01T10:00:00+00:00');

        $entity = new AnimalEntity();
        $entity->setId($id);
        $entity->setClinicId($clinicId);
        $entity->setName('Rex');
        $entity->setSpecies(Species::DOG);
        $entity->setSex(Sex::MALE);
        $entity->setReproductiveStatus(ReproductiveStatus::INTACT);
        $entity->setIsMixedBreed(false);
        $entity->setBreedName('Labrador');
        $entity->setBirthDate($birthDate);
        $entity->setColor('Golden');
        $entity->setPhotoUrl('https://example.com/photo.jpg');
        $entity->setMicrochipNumber('123456789');
        $entity->setTattooNumber('ABC123');
        $entity->setPassportNumber('PASS001');
        $entity->setRegistryType(RegistryType::LOF);
        $entity->setRegistryNumber('LOF12345');
        $entity->setSireNumber('SIRE001');
        $entity->setLifeStatus(LifeStatus::ALIVE);
        $entity->setDeceasedAt(null);
        $entity->setMissingSince(null);
        $entity->setTransferStatus(TransferStatus::NONE);
        $entity->setSoldAt(null);
        $entity->setGivenAt(null);
        $entity->setAuxiliaryContactFirstName('John');
        $entity->setAuxiliaryContactLastName('Doe');
        $entity->setAuxiliaryContactPhoneNumber('+33612345678');
        $entity->setStatus(AnimalStatus::ACTIVE);
        $entity->setCreatedAt($createdAt);
        $entity->setUpdatedAt($updatedAt);

        self::assertSame($id, $entity->getId());
        self::assertSame($clinicId, $entity->getClinicId());
        self::assertSame('Rex', $entity->getName());
        self::assertSame(Species::DOG, $entity->getSpecies());
        self::assertSame(Sex::MALE, $entity->getSex());
        self::assertSame(ReproductiveStatus::INTACT, $entity->getReproductiveStatus());
        self::assertFalse($entity->isMixedBreed());
        self::assertSame('Labrador', $entity->getBreedName());
        self::assertSame($birthDate, $entity->getBirthDate());
        self::assertSame('Golden', $entity->getColor());
        self::assertSame('https://example.com/photo.jpg', $entity->getPhotoUrl());
        self::assertSame('123456789', $entity->getMicrochipNumber());
        self::assertSame('ABC123', $entity->getTattooNumber());
        self::assertSame('PASS001', $entity->getPassportNumber());
        self::assertSame(RegistryType::LOF, $entity->getRegistryType());
        self::assertSame('LOF12345', $entity->getRegistryNumber());
        self::assertSame('SIRE001', $entity->getSireNumber());
        self::assertSame(LifeStatus::ALIVE, $entity->getLifeStatus());
        self::assertNull($entity->getDeceasedAt());
        self::assertNull($entity->getMissingSince());
        self::assertSame(TransferStatus::NONE, $entity->getTransferStatus());
        self::assertNull($entity->getSoldAt());
        self::assertNull($entity->getGivenAt());
        self::assertSame('John', $entity->getAuxiliaryContactFirstName());
        self::assertSame('Doe', $entity->getAuxiliaryContactLastName());
        self::assertSame('+33612345678', $entity->getAuxiliaryContactPhoneNumber());
        self::assertSame(AnimalStatus::ACTIVE, $entity->getStatus());
        self::assertSame($createdAt, $entity->getCreatedAt());
        self::assertSame($updatedAt, $entity->getUpdatedAt());
    }

    public function testOwnershipsCollection(): void
    {
        $animal    = new AnimalEntity();
        $ownership = new OwnershipEntity();

        $animal->addOwnership($ownership);

        self::assertCount(1, $animal->getOwnerships());
        self::assertTrue($animal->getOwnerships()->contains($ownership));
        self::assertSame($animal, $ownership->getAnimal());

        // Adding twice should not duplicate
        $animal->addOwnership($ownership);
        self::assertCount(1, $animal->getOwnerships());

        // Remove ownership
        $animal->removeOwnership($ownership);
        self::assertCount(0, $animal->getOwnerships());
        self::assertNull($ownership->getAnimal());
    }

    public function testRemoveOwnershipWhenNotSet(): void
    {
        $animal     = new AnimalEntity();
        $ownership1 = new OwnershipEntity();
        $ownership2 = new OwnershipEntity();

        $animal->addOwnership($ownership1);

        // Try to remove ownership that doesn't belong to this animal
        $animal->removeOwnership($ownership2);

        self::assertCount(1, $animal->getOwnerships());
    }
}
