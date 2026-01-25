<?php

declare(strict_types=1);

namespace App\Tests\Unit\Animal\Application\Query;

use App\Animal\Application\Query\GetAnimalById\AnimalView;
use App\Animal\Application\Query\GetAnimalById\AuxiliaryContactDto;
use App\Animal\Application\Query\GetAnimalById\IdentificationDto;
use App\Animal\Application\Query\GetAnimalById\LifeCycleDto;
use App\Animal\Application\Query\GetAnimalById\OwnershipDto;
use App\Animal\Application\Query\GetAnimalById\TransferDto;
use App\Animal\Application\Query\SearchAnimals\AnimalListItemView;
use PHPUnit\Framework\TestCase;

final class AnimalDtosTest extends TestCase
{
    public function testAuxiliaryContactDto(): void
    {
        $dto = new AuxiliaryContactDto(
            firstName: 'John',
            lastName: 'Doe',
            phoneNumber: '+33612345678'
        );

        self::assertSame('John', $dto->firstName);
        self::assertSame('Doe', $dto->lastName);
        self::assertSame('+33612345678', $dto->phoneNumber);
    }

    public function testIdentificationDto(): void
    {
        $dto = new IdentificationDto(
            microchipNumber: '123456789',
            tattooNumber: 'ABC123',
            passportNumber: 'PASS001',
            registryType: 'lof',
            registryNumber: 'LOF12345',
            sireNumber: 'SIRE001'
        );

        self::assertSame('123456789', $dto->microchipNumber);
        self::assertSame('ABC123', $dto->tattooNumber);
        self::assertSame('PASS001', $dto->passportNumber);
        self::assertSame('lof', $dto->registryType);
        self::assertSame('LOF12345', $dto->registryNumber);
        self::assertSame('SIRE001', $dto->sireNumber);
    }

    public function testLifeCycleDto(): void
    {
        $dto = new LifeCycleDto(
            lifeStatus: 'alive',
            deceasedAt: null,
            missingSince: null
        );

        self::assertSame('alive', $dto->lifeStatus);
        self::assertNull($dto->deceasedAt);
        self::assertNull($dto->missingSince);
    }

    public function testLifeCycleDtoDeceased(): void
    {
        $dto = new LifeCycleDto(
            lifeStatus: 'deceased',
            deceasedAt: '2024-01-01',
            missingSince: null
        );

        self::assertSame('deceased', $dto->lifeStatus);
        self::assertSame('2024-01-01', $dto->deceasedAt);
        self::assertNull($dto->missingSince);
    }

    public function testOwnershipDto(): void
    {
        $dto = new OwnershipDto(
            clientId: 'client-123',
            role: 'primary',
            status: 'active',
            startedAt: '2024-01-01T10:00:00+00:00',
            endedAt: null
        );

        self::assertSame('client-123', $dto->clientId);
        self::assertSame('primary', $dto->role);
        self::assertSame('active', $dto->status);
        self::assertSame('2024-01-01T10:00:00+00:00', $dto->startedAt);
        self::assertNull($dto->endedAt);
    }

    public function testTransferDto(): void
    {
        $dto = new TransferDto(
            transferStatus: 'none',
            soldAt: null,
            givenAt: null
        );

        self::assertSame('none', $dto->transferStatus);
        self::assertNull($dto->soldAt);
        self::assertNull($dto->givenAt);
    }

    public function testTransferDtoSold(): void
    {
        $dto = new TransferDto(
            transferStatus: 'sold',
            soldAt: '2024-01-01',
            givenAt: null
        );

        self::assertSame('sold', $dto->transferStatus);
        self::assertSame('2024-01-01', $dto->soldAt);
        self::assertNull($dto->givenAt);
    }

    public function testAnimalView(): void
    {
        $ownership1 = new OwnershipDto(
            clientId: 'client-primary',
            role: 'primary',
            status: 'active',
            startedAt: '2024-01-01T10:00:00+00:00',
            endedAt: null
        );

        $ownership2 = new OwnershipDto(
            clientId: 'client-secondary',
            role: 'secondary',
            status: 'active',
            startedAt: '2024-01-01T10:00:00+00:00',
            endedAt: null
        );

        $view = new AnimalView(
            id: '01234567-89ab-cdef-0123-456789abcdef',
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            name: 'Rex',
            species: 'dog',
            sex: 'male',
            reproductiveStatus: 'intact',
            isMixedBreed: false,
            breedName: 'Labrador',
            birthDate: '2020-01-01',
            color: 'Golden',
            photoUrl: 'https://example.com/photo.jpg',
            identification: new IdentificationDto(
                microchipNumber: '123456789',
                tattooNumber: null,
                passportNumber: null,
                registryType: 'none',
                registryNumber: null,
                sireNumber: null
            ),
            lifeCycle: new LifeCycleDto(
                lifeStatus: 'alive',
                deceasedAt: null,
                missingSince: null
            ),
            transfer: new TransferDto(
                transferStatus: 'none',
                soldAt: null,
                givenAt: null
            ),
            auxiliaryContact: new AuxiliaryContactDto(
                firstName: 'John',
                lastName: 'Doe',
                phoneNumber: '+33612345678'
            ),
            status: 'active',
            ownerships: [$ownership1, $ownership2],
            createdAt: '2024-01-01T10:00:00+00:00',
            updatedAt: '2024-01-01T10:00:00+00:00'
        );

        self::assertSame('01234567-89ab-cdef-0123-456789abcdef', $view->id);
        self::assertSame('Rex', $view->name);
        self::assertSame('client-primary', $view->primaryOwnerClientId());
        self::assertSame(['client-secondary'], $view->secondaryOwnerClientIds());
    }

    public function testAnimalViewPrimaryOwnerClientIdReturnsNullWhenNoPrimary(): void
    {
        $ownership = new OwnershipDto(
            clientId: 'client-secondary',
            role: 'secondary',
            status: 'active',
            startedAt: '2024-01-01T10:00:00+00:00',
            endedAt: null
        );

        $view = new AnimalView(
            id: '01234567-89ab-cdef-0123-456789abcdef',
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            name: 'Rex',
            species: 'dog',
            sex: 'male',
            reproductiveStatus: 'intact',
            isMixedBreed: false,
            breedName: null,
            birthDate: null,
            color: null,
            photoUrl: null,
            identification: new IdentificationDto(
                microchipNumber: null,
                tattooNumber: null,
                passportNumber: null,
                registryType: 'none',
                registryNumber: null,
                sireNumber: null
            ),
            lifeCycle: new LifeCycleDto(
                lifeStatus: 'alive',
                deceasedAt: null,
                missingSince: null
            ),
            transfer: new TransferDto(
                transferStatus: 'none',
                soldAt: null,
                givenAt: null
            ),
            auxiliaryContact: null,
            status: 'active',
            ownerships: [$ownership],
            createdAt: '2024-01-01T10:00:00+00:00',
            updatedAt: '2024-01-01T10:00:00+00:00'
        );

        self::assertNull($view->primaryOwnerClientId());
        self::assertSame(['client-secondary'], $view->secondaryOwnerClientIds());
    }

    public function testAnimalListItemView(): void
    {
        $view = new AnimalListItemView(
            id: '01234567-89ab-cdef-0123-456789abcdef',
            name: 'Rex',
            species: 'dog',
            sex: 'male',
            breedName: 'Labrador',
            birthDate: '2020-01-01',
            color: 'Golden',
            microchipNumber: '123456789',
            status: 'active',
            lifeStatus: 'alive',
            primaryOwnerClientId: 'client-123',
            createdAt: '2024-01-01T10:00:00+00:00'
        );

        self::assertSame('01234567-89ab-cdef-0123-456789abcdef', $view->id);
        self::assertSame('Rex', $view->name);
        self::assertSame('dog', $view->species);
        self::assertSame('male', $view->sex);
        self::assertSame('Labrador', $view->breedName);
        self::assertSame('2020-01-01', $view->birthDate);
        self::assertSame('Golden', $view->color);
        self::assertSame('123456789', $view->microchipNumber);
        self::assertSame('active', $view->status);
        self::assertSame('alive', $view->lifeStatus);
        self::assertSame('client-123', $view->primaryOwnerClientId);
        self::assertSame('2024-01-01T10:00:00+00:00', $view->createdAt);
    }
}
