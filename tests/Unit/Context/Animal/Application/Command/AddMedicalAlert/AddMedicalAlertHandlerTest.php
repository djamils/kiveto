<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Animal\Application\Command\AddMedicalAlert;

use App\Context\Animal\Application\Command\AddMedicalAlert\AddMedicalAlert;
use App\Context\Animal\Application\Command\AddMedicalAlert\AddMedicalAlertHandler;
use App\Context\Animal\Domain\Animal;
use App\Context\Animal\Domain\Repository\AnimalRepositoryInterface;
use App\Context\Animal\Domain\ValueObject\AnimalId;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use App\Context\Animal\Domain\ValueObject\Identification;
use App\Context\Animal\Domain\ValueObject\LifeCycle;
use App\Context\Animal\Domain\ValueObject\MedicalAlertKind;
use App\Context\Animal\Domain\ValueObject\ReproductiveStatus;
use App\Context\Animal\Domain\ValueObject\Sex;
use App\Context\Animal\Domain\ValueObject\Species;
use App\Context\Animal\Domain\ValueObject\Transfer;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\TestCase;

final class AddMedicalAlertHandlerTest extends TestCase
{
    private const string CLINIC_ID = '12345678-9abc-def0-1234-56789abcdef0';
    private const string ANIMAL_ID = '01234567-89ab-cdef-0123-456789abcdef';

    public function testHandleAddsAlertAndSavesAnimal(): void
    {
        $animal = $this->createAnimal();
        $now    = new \DateTimeImmutable('2024-06-01T10:00:00+00:00');

        $repository = $this->createMock(AnimalRepositoryInterface::class);
        $clock      = $this->createMock(ClockInterface::class);

        $repository->expects(self::once())
            ->method('get')
            ->with(
                self::callback(static fn (ClinicId $id) => self::CLINIC_ID === $id->toString()),
                self::callback(static fn (AnimalId $id) => self::ANIMAL_ID === $id->toString()),
            )
            ->willReturn($animal)
        ;

        $clock->expects(self::once())->method('now')->willReturn($now);

        $repository->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (Animal $saved): bool {
                $alerts = $saved->medicalAlerts();

                return 1 === \count($alerts)
                    && MedicalAlertKind::ALLERGY === $alerts[0]->kind
                    && 'Pénicilline' === $alerts[0]->label
                    && 'Choc en 2023' === $alerts[0]->note;
            }))
        ;

        $handler = new AddMedicalAlertHandler($repository, $clock);
        $handler(new AddMedicalAlert(
            clinicId: self::CLINIC_ID,
            animalId: self::ANIMAL_ID,
            kind: 'ALLERGY',
            label: 'Pénicilline',
            note: 'Choc en 2023',
        ));

        self::assertSame($now, $animal->updatedAt());
    }

    public function testHandleThrowsOnUnknownKind(): void
    {
        $animal = $this->createAnimal();

        $repository = $this->createMock(AnimalRepositoryInterface::class);
        $clock      = $this->createMock(ClockInterface::class);

        $repository->expects(self::once())->method('get')->willReturn($animal);
        $repository->expects(self::never())->method('save');
        $clock->expects(self::never())->method('now');

        $handler = new AddMedicalAlertHandler($repository, $clock);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown medical alert kind');

        $handler(new AddMedicalAlert(
            clinicId: self::CLINIC_ID,
            animalId: self::ANIMAL_ID,
            kind: 'NOT_A_KIND',
            label: 'Pénicilline',
        ));
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
