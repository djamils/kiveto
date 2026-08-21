<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Animal\Infrastructure\Persistence\Doctrine\Entity;

use App\Context\Animal\Domain\ValueObject\MedicalAlertKind;
use App\Context\Animal\Infrastructure\Persistence\Doctrine\Entity\AnimalEntity;
use App\Context\Animal\Infrastructure\Persistence\Doctrine\Entity\MedicalAlertEntity;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class MedicalAlertEntityTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $id     = Uuid::v7();
        $animal = new AnimalEntity();

        $entity = new MedicalAlertEntity();
        $entity->setId($id);
        $entity->setAnimal($animal);
        $entity->setKind(MedicalAlertKind::ALLERGY);
        $entity->setLabel('Pénicilline');
        $entity->setNote('Choc en 2023');

        self::assertSame($id, $entity->getId());
        self::assertSame($animal, $entity->getAnimal());
        self::assertSame(MedicalAlertKind::ALLERGY, $entity->getKind());
        self::assertSame('Pénicilline', $entity->getLabel());
        self::assertSame('Choc en 2023', $entity->getNote());
    }

    public function testAnimalAndNoteAreNullable(): void
    {
        $entity = new MedicalAlertEntity();
        $entity->setKind(MedicalAlertKind::CHRONIC_CONDITION);
        $entity->setLabel('Diabète');
        $entity->setNote(null);
        $entity->setAnimal(null);

        self::assertNull($entity->getAnimal());
        self::assertNull($entity->getNote());
        self::assertSame(MedicalAlertKind::CHRONIC_CONDITION, $entity->getKind());
    }

    public function testAddMedicalAlertOnAnimalIsIdempotentAndSetsBothSides(): void
    {
        $animal = new AnimalEntity();
        $alert  = new MedicalAlertEntity();
        $alert->setId(Uuid::v7());
        $alert->setKind(MedicalAlertKind::ALLERGY);
        $alert->setLabel('Pénicilline');

        $animal->addMedicalAlert($alert);
        $animal->addMedicalAlert($alert);

        self::assertCount(1, $animal->getMedicalAlerts());
        self::assertSame($animal, $alert->getAnimal());
    }
}
