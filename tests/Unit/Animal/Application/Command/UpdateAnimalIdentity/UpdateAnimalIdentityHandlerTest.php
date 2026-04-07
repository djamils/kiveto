<?php

declare(strict_types=1);

namespace App\Tests\Unit\Animal\Application\Command\UpdateAnimalIdentity;

use App\Animal\Application\Command\UpdateAnimalIdentity\UpdateAnimalIdentity;
use App\Animal\Application\Command\UpdateAnimalIdentity\UpdateAnimalIdentityHandler;
use App\Animal\Domain\Animal;
use App\Animal\Domain\Exception\AnimalClinicMismatchException;
use App\Animal\Domain\Exception\MicrochipAlreadyUsedException;
use App\Animal\Domain\Repository\AnimalRepositoryInterface;
use App\Animal\Domain\ValueObject\AnimalId;
use App\Animal\Domain\ValueObject\AnimalStatus;
use App\Animal\Domain\ValueObject\Identification;
use App\Animal\Domain\ValueObject\LifeCycle;
use App\Animal\Domain\ValueObject\ReproductiveStatus;
use App\Animal\Domain\ValueObject\Sex;
use App\Animal\Domain\ValueObject\Species;
use App\Animal\Domain\ValueObject\Transfer;
use App\Clinic\Domain\ValueObject\ClinicId;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\TestCase;

final class UpdateAnimalIdentityHandlerTest extends TestCase
{
    public function testHandleUpdatesAnimalIdentity(): void
    {
        $clinicId  = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');
        $animalId  = AnimalId::fromString('01234567-89ab-cdef-0123-456789abcdef');
        $now       = new \DateTimeImmutable('2024-01-02 14:00:00');
        $createdAt = new \DateTimeImmutable('2024-01-01 10:00:00');

        $animal = Animal::reconstituteFromPersistence(
            id: $animalId,
            clinicId: $clinicId,
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
            status: AnimalStatus::ACTIVE,
            ownerships: [
                new \App\Animal\Domain\ValueObject\Ownership(
                    clientId: 'client-123',
                    role: \App\Animal\Domain\ValueObject\OwnershipRole::PRIMARY,
                    status: \App\Animal\Domain\ValueObject\OwnershipStatus::ACTIVE,
                    startedAt: $createdAt,
                    endedAt: null
                ),
            ],
            createdAt: $createdAt,
            updatedAt: $createdAt
        );

        $command = new UpdateAnimalIdentity(
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            animalId: '01234567-89ab-cdef-0123-456789abcdef',
            name: 'Rex Updated',
            species: 'cat',
            sex: 'female',
            reproductiveStatus: 'neutered',
            isMixedBreed: true,
            breedName: 'Mixed',
            birthDate: '2021-05-15',
            color: 'Black',
            photoUrl: 'https://example.com/updated.jpg',
            microchipNumber: '987654321',
            tattooNumber: null,
            passportNumber: null,
            registryType: 'none',
            registryNumber: null,
            sireNumber: null,
            auxiliaryContactFirstName: null,
            auxiliaryContactLastName: null,
            auxiliaryContactPhoneNumber: null,
        );

        $repository = $this->createMock(AnimalRepositoryInterface::class);
        $eventBus   = $this->createMock(EventBusInterface::class);
        $clock      = $this->createMock(ClockInterface::class);

        $repository->expects(self::once())
            ->method('get')
            ->with(
                self::callback(fn (ClinicId $id) => $id->equals($clinicId)),
                self::callback(fn (AnimalId $id) => $id->equals($animalId))
            )
            ->willReturn($animal)
        ;

        $clock->expects(self::once())
            ->method('now')
            ->willReturn($now)
        ;

        $repository->expects(self::once())
            ->method('existsByMicrochip')
            ->willReturn(false)
        ;

        $repository->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(Animal::class))
        ;

        $eventBus->expects(self::once())
            ->method('publish')
        ;

        $handler = new UpdateAnimalIdentityHandler($repository, $eventBus, $clock);
        $handler($command);
    }

    public function testHandleThrowsExceptionWhenClinicMismatch(): void
    {
        $correctClinicId = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');
        $animalId        = AnimalId::fromString('01234567-89ab-cdef-0123-456789abcdef');
        $createdAt       = new \DateTimeImmutable('2024-01-01 10:00:00');

        $animal = Animal::reconstituteFromPersistence(
            id: $animalId,
            clinicId: $correctClinicId,
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
            status: AnimalStatus::ACTIVE,
            ownerships: [
                new \App\Animal\Domain\ValueObject\Ownership(
                    clientId: 'client-123',
                    role: \App\Animal\Domain\ValueObject\OwnershipRole::PRIMARY,
                    status: \App\Animal\Domain\ValueObject\OwnershipStatus::ACTIVE,
                    startedAt: $createdAt,
                    endedAt: null
                ),
            ],
            createdAt: $createdAt,
            updatedAt: $createdAt
        );

        $command = new UpdateAnimalIdentity(
            clinicId: 'ffffffff-ffff-ffff-ffff-ffffffffffff',
            animalId: '01234567-89ab-cdef-0123-456789abcdef',
            name: 'Rex',
            species: 'dog',
            sex: 'male',
            reproductiveStatus: 'intact',
            isMixedBreed: false,
            breedName: null,
            birthDate: null,
            color: null,
            photoUrl: null,
            microchipNumber: null,
            tattooNumber: null,
            passportNumber: null,
            registryType: 'none',
            registryNumber: null,
            sireNumber: null,
            auxiliaryContactFirstName: null,
            auxiliaryContactLastName: null,
            auxiliaryContactPhoneNumber: null,
        );

        $repository = $this->createMock(AnimalRepositoryInterface::class);
        $eventBus   = $this->createStub(EventBusInterface::class);
        $clock      = $this->createStub(ClockInterface::class);

        $repository->expects(self::once())
            ->method('get')
            ->willReturn($animal)
        ;

        $this->expectException(AnimalClinicMismatchException::class);

        $handler = new UpdateAnimalIdentityHandler($repository, $eventBus, $clock);
        $handler($command);
    }

    public function testHandleThrowsExceptionWhenMicrochipAlreadyExists(): void
    {
        $clinicId  = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');
        $animalId  = AnimalId::fromString('01234567-89ab-cdef-0123-456789abcdef');
        $createdAt = new \DateTimeImmutable('2024-01-01 10:00:00');

        $animal = Animal::reconstituteFromPersistence(
            id: $animalId,
            clinicId: $clinicId,
            name: 'Rex',
            species: Species::DOG,
            sex: Sex::MALE,
            reproductiveStatus: ReproductiveStatus::INTACT,
            isMixedBreed: false,
            breedName: null,
            birthDate: null,
            color: null,
            photoUrl: null,
            identification: new Identification(
                microchipNumber: '123456789',
                tattooNumber: null,
                passportNumber: null,
                registryType: \App\Animal\Domain\ValueObject\RegistryType::NONE,
                registryNumber: null,
                sireNumber: null,
            ),
            lifeCycle: LifeCycle::alive(),
            transfer: Transfer::none(),
            auxiliaryContact: null,
            status: AnimalStatus::ACTIVE,
            ownerships: [
                new \App\Animal\Domain\ValueObject\Ownership(
                    clientId: 'client-123',
                    role: \App\Animal\Domain\ValueObject\OwnershipRole::PRIMARY,
                    status: \App\Animal\Domain\ValueObject\OwnershipStatus::ACTIVE,
                    startedAt: $createdAt,
                    endedAt: null
                ),
            ],
            createdAt: $createdAt,
            updatedAt: $createdAt
        );

        $command = new UpdateAnimalIdentity(
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            animalId: '01234567-89ab-cdef-0123-456789abcdef',
            name: 'Rex',
            species: 'dog',
            sex: 'male',
            reproductiveStatus: 'intact',
            isMixedBreed: false,
            breedName: null,
            birthDate: null,
            color: null,
            photoUrl: null,
            microchipNumber: '987654321', // Different microchip
            tattooNumber: null,
            passportNumber: null,
            registryType: 'none',
            registryNumber: null,
            sireNumber: null,
            auxiliaryContactFirstName: null,
            auxiliaryContactLastName: null,
            auxiliaryContactPhoneNumber: null,
        );

        $repository = $this->createMock(AnimalRepositoryInterface::class);
        $eventBus   = $this->createStub(EventBusInterface::class);
        $clock      = $this->createStub(ClockInterface::class);

        $repository->expects(self::once())
            ->method('get')
            ->willReturn($animal)
        ;

        $repository->expects(self::once())
            ->method('existsByMicrochip')
            ->with(
                self::callback(fn (ClinicId $id) => $id->equals($clinicId)),
                '987654321',
                self::callback(fn (AnimalId $id) => $id->equals($animalId))
            )
            ->willReturn(true) // Microchip already exists
        ;

        $this->expectException(MicrochipAlreadyUsedException::class);

        $handler = new UpdateAnimalIdentityHandler($repository, $eventBus, $clock);
        $handler($command);
    }

    public function testHandleUpdatesAnimalIdentityWithAuxiliaryContact(): void
    {
        $clinicId  = ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0');
        $animalId  = AnimalId::fromString('01234567-89ab-cdef-0123-456789abcdef');
        $now       = new \DateTimeImmutable('2024-01-02 14:00:00');
        $createdAt = new \DateTimeImmutable('2024-01-01 10:00:00');

        $animal = Animal::reconstituteFromPersistence(
            id: $animalId,
            clinicId: $clinicId,
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
            status: AnimalStatus::ACTIVE,
            ownerships: [
                new \App\Animal\Domain\ValueObject\Ownership(
                    clientId: 'client-123',
                    role: \App\Animal\Domain\ValueObject\OwnershipRole::PRIMARY,
                    status: \App\Animal\Domain\ValueObject\OwnershipStatus::ACTIVE,
                    startedAt: $createdAt,
                    endedAt: null
                ),
            ],
            createdAt: $createdAt,
            updatedAt: $createdAt
        );

        $command = new UpdateAnimalIdentity(
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            animalId: '01234567-89ab-cdef-0123-456789abcdef',
            name: 'Rex',
            species: 'dog',
            sex: 'male',
            reproductiveStatus: 'intact',
            isMixedBreed: false,
            breedName: null,
            birthDate: null,
            color: null,
            photoUrl: null,
            microchipNumber: null,
            tattooNumber: null,
            passportNumber: null,
            registryType: 'none',
            registryNumber: null,
            sireNumber: null,
            auxiliaryContactFirstName: 'John',
            auxiliaryContactLastName: 'Doe',
            auxiliaryContactPhoneNumber: '+33612345678',
        );

        $repository = $this->createMock(AnimalRepositoryInterface::class);
        $eventBus   = $this->createMock(EventBusInterface::class);
        $clock      = $this->createMock(ClockInterface::class);

        $repository->expects(self::once())
            ->method('get')
            ->with(
                self::callback(fn (ClinicId $id) => $id->equals($clinicId)),
                self::callback(fn (AnimalId $id) => $id->equals($animalId))
            )
            ->willReturn($animal)
        ;

        $clock->expects(self::once())
            ->method('now')
            ->willReturn($now)
        ;

        $repository->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(Animal::class))
        ;

        $eventBus->expects(self::once())
            ->method('publish')
        ;

        $handler = new UpdateAnimalIdentityHandler($repository, $eventBus, $clock);
        $handler($command);

        // Verify that the animal has the auxiliary contact
        self::assertNotNull($animal->auxiliaryContact());
        self::assertSame('John', $animal->auxiliaryContact()->firstName);
        self::assertSame('Doe', $animal->auxiliaryContact()->lastName);
        self::assertSame('+33612345678', $animal->auxiliaryContact()->phoneNumber);
    }
}
