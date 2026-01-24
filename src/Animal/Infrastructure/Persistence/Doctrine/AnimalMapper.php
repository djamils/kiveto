<?php

declare(strict_types=1);

namespace App\Animal\Infrastructure\Persistence\Doctrine;

use App\Animal\Domain\Animal;
use App\Animal\Domain\Enum\AnimalStatus;
use App\Animal\Domain\Enum\LifeStatus;
use App\Animal\Domain\Enum\OwnershipRole;
use App\Animal\Domain\Enum\OwnershipStatus;
use App\Animal\Domain\Enum\RegistryType;
use App\Animal\Domain\Enum\ReproductiveStatus;
use App\Animal\Domain\Enum\Sex;
use App\Animal\Domain\Enum\Species;
use App\Animal\Domain\Enum\TransferStatus;
use App\Animal\Domain\ValueObject\AnimalId;
use App\Animal\Domain\ValueObject\AuxiliaryContact;
use App\Animal\Domain\ValueObject\Identification;
use App\Animal\Domain\ValueObject\LifeCycle;
use App\Animal\Domain\ValueObject\Ownership;
use App\Animal\Domain\ValueObject\Transfer;
use App\Animal\Infrastructure\Persistence\Doctrine\Entity\AnimalEntity;
use App\Animal\Infrastructure\Persistence\Doctrine\Entity\OwnershipEntity;
use App\Clinic\Domain\ValueObject\ClinicId;

final class AnimalMapper
{
    public function toDomain(AnimalEntity $entity): Animal
    {
        // Map identification
        $identification = new Identification(
            microchipNumber: $entity->microchipNumber,
            tattooNumber: $entity->tattooNumber,
            passportNumber: $entity->passportNumber,
            registryType: RegistryType::from($entity->registryType),
            registryNumber: $entity->registryNumber,
            sireNumber: $entity->sireNumber,
        );

        // Map lifecycle
        $lifeCycle = new LifeCycle(
            lifeStatus: LifeStatus::from($entity->lifeStatus),
            deceasedAt: $entity->deceasedAt,
            missingSince: $entity->missingSince,
        );

        // Map transfer
        $transfer = new Transfer(
            transferStatus: TransferStatus::from($entity->transferStatus),
            soldAt: $entity->soldAt,
            givenAt: $entity->givenAt,
        );

        // Map auxiliary contact
        $auxiliaryContact    = null;
        $hasAuxiliaryContact = null !== $entity->auxiliaryContactFirstName
            && null !== $entity->auxiliaryContactLastName
            && null !== $entity->auxiliaryContactPhoneNumber;

        if ($hasAuxiliaryContact) {
            \assert(null !== $entity->auxiliaryContactFirstName);
            \assert(null !== $entity->auxiliaryContactLastName);
            \assert(null !== $entity->auxiliaryContactPhoneNumber);

            $auxiliaryContact = new AuxiliaryContact(
                firstName: $entity->auxiliaryContactFirstName,
                lastName: $entity->auxiliaryContactLastName,
                phoneNumber: $entity->auxiliaryContactPhoneNumber,
            );
        }

        // Map ownerships
        $ownerships = [];
        foreach ($entity->ownerships as $ownershipEntity) {
            $ownerships[] = new Ownership(
                clientId: $ownershipEntity->clientId,
                role: OwnershipRole::from($ownershipEntity->role),
                status: OwnershipStatus::from($ownershipEntity->status),
                startedAt: $ownershipEntity->startedAt,
                endedAt: $ownershipEntity->endedAt,
            );
        }

        return Animal::reconstituteFromPersistence(
            id: AnimalId::fromString($entity->id),
            clinicId: ClinicId::fromString($entity->clinicId),
            name: $entity->name,
            species: Species::from($entity->species),
            sex: Sex::from($entity->sex),
            reproductiveStatus: ReproductiveStatus::from($entity->reproductiveStatus),
            isMixedBreed: $entity->isMixedBreed,
            breedName: $entity->breedName,
            birthDate: $entity->birthDate,
            color: $entity->color,
            photoUrl: $entity->photoUrl,
            identification: $identification,
            lifeCycle: $lifeCycle,
            transfer: $transfer,
            auxiliaryContact: $auxiliaryContact,
            status: AnimalStatus::from($entity->status),
            ownerships: $ownerships,
            createdAt: $entity->createdAt,
            updatedAt: $entity->updatedAt,
        );
    }

    public function toEntity(Animal $animal): AnimalEntity
    {
        $entity = new AnimalEntity();

        $entity->id                 = $animal->id()->value();
        $entity->clinicId           = $animal->clinicId()->toString();
        $entity->name               = $animal->name();
        $entity->species            = $animal->species()->value;
        $entity->sex                = $animal->sex()->value;
        $entity->reproductiveStatus = $animal->reproductiveStatus()->value;
        $entity->isMixedBreed       = $animal->isMixedBreed();
        $entity->breedName          = $animal->breedName();
        $entity->birthDate          = $animal->birthDate();
        $entity->color              = $animal->color();
        $entity->photoUrl           = $animal->photoUrl();

        // Identification
        $identification          = $animal->identification();
        $entity->microchipNumber = $identification->microchipNumber;
        $entity->tattooNumber    = $identification->tattooNumber;
        $entity->passportNumber  = $identification->passportNumber;
        $entity->registryType    = $identification->registryType->value;
        $entity->registryNumber  = $identification->registryNumber;
        $entity->sireNumber      = $identification->sireNumber;

        // LifeCycle
        $lifeCycle            = $animal->lifeCycle();
        $entity->lifeStatus   = $lifeCycle->lifeStatus->value;
        $entity->deceasedAt   = $lifeCycle->deceasedAt;
        $entity->missingSince = $lifeCycle->missingSince;

        // Transfer
        $transfer               = $animal->transfer();
        $entity->transferStatus = $transfer->transferStatus->value;
        $entity->soldAt         = $transfer->soldAt;
        $entity->givenAt        = $transfer->givenAt;

        // AuxiliaryContact
        $auxiliaryContact = $animal->auxiliaryContact();
        if (null !== $auxiliaryContact) {
            $entity->auxiliaryContactFirstName   = $auxiliaryContact->firstName;
            $entity->auxiliaryContactLastName    = $auxiliaryContact->lastName;
            $entity->auxiliaryContactPhoneNumber = $auxiliaryContact->phoneNumber;
        } else {
            $entity->auxiliaryContactFirstName   = null;
            $entity->auxiliaryContactLastName    = null;
            $entity->auxiliaryContactPhoneNumber = null;
        }

        $entity->status    = $animal->status()->value;
        $entity->createdAt = $animal->createdAt();
        $entity->updatedAt = $animal->updatedAt();

        return $entity;
    }

    public function updateEntity(Animal $animal, AnimalEntity $entity): void
    {
        $entity->name               = $animal->name();
        $entity->species            = $animal->species()->value;
        $entity->sex                = $animal->sex()->value;
        $entity->reproductiveStatus = $animal->reproductiveStatus()->value;
        $entity->isMixedBreed       = $animal->isMixedBreed();
        $entity->breedName          = $animal->breedName();
        $entity->birthDate          = $animal->birthDate();
        $entity->color              = $animal->color();
        $entity->photoUrl           = $animal->photoUrl();

        // Identification
        $identification          = $animal->identification();
        $entity->microchipNumber = $identification->microchipNumber;
        $entity->tattooNumber    = $identification->tattooNumber;
        $entity->passportNumber  = $identification->passportNumber;
        $entity->registryType    = $identification->registryType->value;
        $entity->registryNumber  = $identification->registryNumber;
        $entity->sireNumber      = $identification->sireNumber;

        // LifeCycle
        $lifeCycle            = $animal->lifeCycle();
        $entity->lifeStatus   = $lifeCycle->lifeStatus->value;
        $entity->deceasedAt   = $lifeCycle->deceasedAt;
        $entity->missingSince = $lifeCycle->missingSince;

        // Transfer
        $transfer               = $animal->transfer();
        $entity->transferStatus = $transfer->transferStatus->value;
        $entity->soldAt         = $transfer->soldAt;
        $entity->givenAt        = $transfer->givenAt;

        // AuxiliaryContact
        $auxiliaryContact = $animal->auxiliaryContact();
        if (null !== $auxiliaryContact) {
            $entity->auxiliaryContactFirstName   = $auxiliaryContact->firstName;
            $entity->auxiliaryContactLastName    = $auxiliaryContact->lastName;
            $entity->auxiliaryContactPhoneNumber = $auxiliaryContact->phoneNumber;
        } else {
            $entity->auxiliaryContactFirstName   = null;
            $entity->auxiliaryContactLastName    = null;
            $entity->auxiliaryContactPhoneNumber = null;
        }

        $entity->status    = $animal->status()->value;
        $entity->updatedAt = $animal->updatedAt();

        // Sync ownerships
        $entity->ownerships->clear();

        foreach ($animal->ownerships() as $ownership) {
            $ownershipEntity            = new OwnershipEntity();
            $ownershipEntity->animal    = $entity;
            $ownershipEntity->clientId  = $ownership->clientId;
            $ownershipEntity->role      = $ownership->role->value;
            $ownershipEntity->status    = $ownership->status->value;
            $ownershipEntity->startedAt = $ownership->startedAt;
            $ownershipEntity->endedAt   = $ownership->endedAt;

            $entity->ownerships->add($ownershipEntity);
        }
    }
}
