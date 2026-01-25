<?php

declare(strict_types=1);

namespace App\Animal\Infrastructure\Persistence\Doctrine;

use App\Animal\Domain\Animal;
use App\Animal\Domain\ValueObject\AnimalId;
use App\Animal\Domain\ValueObject\AuxiliaryContact;
use App\Animal\Domain\ValueObject\Identification;
use App\Animal\Domain\ValueObject\LifeCycle;
use App\Animal\Domain\ValueObject\Ownership;
use App\Animal\Domain\ValueObject\Transfer;
use App\Animal\Infrastructure\Persistence\Doctrine\Entity\AnimalEntity;
use App\Animal\Infrastructure\Persistence\Doctrine\Entity\OwnershipEntity;
use App\Clinic\Domain\ValueObject\ClinicId;
use Symfony\Component\Uid\Uuid;

final class AnimalMapper
{
    public function toDomain(AnimalEntity $entity): Animal
    {
        // Map identification
        $identification = new Identification(
            microchipNumber: $entity->getMicrochipNumber(),
            tattooNumber: $entity->getTattooNumber(),
            passportNumber: $entity->getPassportNumber(),
            registryType: $entity->getRegistryType(),
            registryNumber: $entity->getRegistryNumber(),
            sireNumber: $entity->getSireNumber(),
        );

        // Map lifecycle
        $lifeCycle = new LifeCycle(
            lifeStatus: $entity->getLifeStatus(),
            deceasedAt: $entity->getDeceasedAt(),
            missingSince: $entity->getMissingSince(),
        );

        // Map transfer
        $transfer = new Transfer(
            transferStatus: $entity->getTransferStatus(),
            soldAt: $entity->getSoldAt(),
            givenAt: $entity->getGivenAt(),
        );

        // Map auxiliary contact
        $auxiliaryContact    = null;
        $hasAuxiliaryContact = null !== $entity->getAuxiliaryContactFirstName()
            && null !== $entity->getAuxiliaryContactLastName()
            && null !== $entity->getAuxiliaryContactPhoneNumber();

        if ($hasAuxiliaryContact) {
            \assert(null !== $entity->getAuxiliaryContactFirstName());
            \assert(null !== $entity->getAuxiliaryContactLastName());
            \assert(null !== $entity->getAuxiliaryContactPhoneNumber());

            $auxiliaryContact = new AuxiliaryContact(
                firstName: $entity->getAuxiliaryContactFirstName(),
                lastName: $entity->getAuxiliaryContactLastName(),
                phoneNumber: $entity->getAuxiliaryContactPhoneNumber(),
            );
        }

        // Map ownerships
        $ownerships = [];
        foreach ($entity->getOwnerships() as $ownershipEntity) {
            $ownerships[] = new Ownership(
                clientId: $ownershipEntity->getClientId()->toString(),
                role: $ownershipEntity->getRole(),
                status: $ownershipEntity->getStatus(),
                startedAt: $ownershipEntity->getStartedAt(),
                endedAt: $ownershipEntity->getEndedAt(),
            );
        }

        return Animal::reconstituteFromPersistence(
            id: AnimalId::fromString($entity->getId()->toString()),
            clinicId: ClinicId::fromString($entity->getClinicId()->toString()),
            name: $entity->getName(),
            species: $entity->getSpecies(),
            sex: $entity->getSex(),
            reproductiveStatus: $entity->getReproductiveStatus(),
            isMixedBreed: $entity->isMixedBreed(),
            breedName: $entity->getBreedName(),
            birthDate: $entity->getBirthDate(),
            color: $entity->getColor(),
            photoUrl: $entity->getPhotoUrl(),
            identification: $identification,
            lifeCycle: $lifeCycle,
            transfer: $transfer,
            auxiliaryContact: $auxiliaryContact,
            ownerships: $ownerships,
            status: $entity->getStatus(),
            createdAt: $entity->getCreatedAt(),
            updatedAt: $entity->getUpdatedAt(),
        );
    }

    public function toEntity(Animal $animal): AnimalEntity
    {
        $entity = new AnimalEntity();

        $entity->setId(Uuid::fromString($animal->id()->toString()));
        $entity->setClinicId(Uuid::fromString($animal->clinicId()->toString()));
        $entity->setName($animal->name());
        $entity->setSpecies($animal->species());
        $entity->setSex($animal->sex());
        $entity->setReproductiveStatus($animal->reproductiveStatus());
        $entity->setIsMixedBreed($animal->isMixedBreed());
        $entity->setBreedName($animal->breedName());
        $entity->setBirthDate($animal->birthDate());
        $entity->setColor($animal->color());
        $entity->setPhotoUrl($animal->photoUrl());

        // Identification
        $identification = $animal->identification();
        $entity->setMicrochipNumber($identification->microchipNumber);
        $entity->setTattooNumber($identification->tattooNumber);
        $entity->setPassportNumber($identification->passportNumber);
        $entity->setRegistryType($identification->registryType);
        $entity->setRegistryNumber($identification->registryNumber);
        $entity->setSireNumber($identification->sireNumber);

        // Life cycle
        $lifeCycle = $animal->lifeCycle();
        $entity->setLifeStatus($lifeCycle->lifeStatus);
        $entity->setDeceasedAt($lifeCycle->deceasedAt);
        $entity->setMissingSince($lifeCycle->missingSince);

        // Transfer
        $transfer = $animal->transfer();
        $entity->setTransferStatus($transfer->transferStatus);
        $entity->setSoldAt($transfer->soldAt);
        $entity->setGivenAt($transfer->givenAt);

        // Auxiliary contact
        $auxiliaryContact = $animal->auxiliaryContact();
        if (null !== $auxiliaryContact) {
            $entity->setAuxiliaryContactFirstName($auxiliaryContact->firstName);
            $entity->setAuxiliaryContactLastName($auxiliaryContact->lastName);
            $entity->setAuxiliaryContactPhoneNumber($auxiliaryContact->phoneNumber);
        } else {
            $entity->setAuxiliaryContactFirstName(null);
            $entity->setAuxiliaryContactLastName(null);
            $entity->setAuxiliaryContactPhoneNumber(null);
        }

        $entity->setStatus($animal->status());
        $entity->setCreatedAt($animal->createdAt());
        $entity->setUpdatedAt($animal->updatedAt());

        // Clear and rebuild ownerships
        foreach ($entity->getOwnerships() as $existingOwnership) {
            $entity->removeOwnership($existingOwnership);
        }

        foreach ($animal->ownerships() as $ownership) {
            $ownershipEntity = new OwnershipEntity();
            $ownershipEntity->setClientId(Uuid::fromString($ownership->clientId));
            $ownershipEntity->setRole($ownership->role);
            $ownershipEntity->setStatus($ownership->status);
            $ownershipEntity->setStartedAt($ownership->startedAt);
            $ownershipEntity->setEndedAt($ownership->endedAt);
            $entity->addOwnership($ownershipEntity);
        }

        return $entity;
    }
}
