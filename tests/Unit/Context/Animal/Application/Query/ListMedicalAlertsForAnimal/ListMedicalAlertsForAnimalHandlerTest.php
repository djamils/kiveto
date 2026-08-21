<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Animal\Application\Query\ListMedicalAlertsForAnimal;

use App\Context\Animal\Application\Query\ListMedicalAlertsForAnimal\ListMedicalAlertsForAnimal;
use App\Context\Animal\Application\Query\ListMedicalAlertsForAnimal\ListMedicalAlertsForAnimalHandler;
use App\Context\Animal\Domain\Animal;
use App\Context\Animal\Domain\Repository\AnimalRepositoryInterface;
use App\Context\Animal\Domain\ValueObject\AnimalId;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use App\Context\Animal\Domain\ValueObject\Identification;
use App\Context\Animal\Domain\ValueObject\LifeCycle;
use App\Context\Animal\Domain\ValueObject\MedicalAlert;
use App\Context\Animal\Domain\ValueObject\MedicalAlertKind;
use App\Context\Animal\Domain\ValueObject\ReproductiveStatus;
use App\Context\Animal\Domain\ValueObject\Sex;
use App\Context\Animal\Domain\ValueObject\Species;
use App\Context\Animal\Domain\ValueObject\Transfer;
use PHPUnit\Framework\TestCase;

final class ListMedicalAlertsForAnimalHandlerTest extends TestCase
{
    private const string CLINIC_ID = '12345678-9abc-def0-1234-56789abcdef0';
    private const string ANIMAL_ID = '01234567-89ab-cdef-0123-456789abcdef';

    public function testHandleMapsAlertsToViews(): void
    {
        $now    = new \DateTimeImmutable('2024-01-01T10:00:00+00:00');
        $animal = $this->createAnimal();

        $allergy = MedicalAlert::create(MedicalAlertKind::ALLERGY, 'Pénicilline', 'Choc en 2023');
        $chronic = MedicalAlert::create(MedicalAlertKind::CHRONIC_CONDITION, 'Diabète');
        $animal->addMedicalAlert($allergy, $now);
        $animal->addMedicalAlert($chronic, $now);

        $repository = $this->createMock(AnimalRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('findById')
            ->with(
                self::callback(static fn (ClinicId $id) => self::CLINIC_ID === $id->toString()),
                self::callback(static fn (AnimalId $id) => self::ANIMAL_ID === $id->toString()),
            )
            ->willReturn($animal)
        ;

        $handler = new ListMedicalAlertsForAnimalHandler($repository);
        $views   = $handler(new ListMedicalAlertsForAnimal(self::CLINIC_ID, self::ANIMAL_ID));

        self::assertCount(2, $views);

        self::assertSame($allergy->id, $views[0]->id);
        self::assertSame('ALLERGY', $views[0]->kind);
        self::assertSame('Pénicilline', $views[0]->label);
        self::assertSame('Choc en 2023', $views[0]->note);

        self::assertSame($chronic->id, $views[1]->id);
        self::assertSame('CHRONIC_CONDITION', $views[1]->kind);
        self::assertSame('Diabète', $views[1]->label);
        self::assertNull($views[1]->note);
    }

    public function testHandleReturnsEmptyListWhenAnimalIsNotFound(): void
    {
        $repository = $this->createMock(AnimalRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('findById')
            ->willReturn(null)
        ;

        $handler = new ListMedicalAlertsForAnimalHandler($repository);

        self::assertSame([], $handler(new ListMedicalAlertsForAnimal(self::CLINIC_ID, self::ANIMAL_ID)));
    }

    private function createAnimal(): Animal
    {
        return Animal::create(
            id: AnimalId::fromString(self::ANIMAL_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            name: 'Rex',
            species: Species::DOG,
            sex: Sex::MALE,
            reproductiveStatus: ReproductiveStatus::INTACT,
            isMixedBreed: false,
            breedName: null,
            birthDate: null,
            color: null,
            photoUrl: null,
            identification: Identification::createEmpty(),
            lifeCycle: LifeCycle::alive(),
            transfer: Transfer::none(),
            auxiliaryContact: null,
            primaryOwnerClientId: 'client-123',
            secondaryOwnerClientIds: [],
            now: new \DateTimeImmutable('2024-01-01T10:00:00+00:00'),
        );
    }
}
