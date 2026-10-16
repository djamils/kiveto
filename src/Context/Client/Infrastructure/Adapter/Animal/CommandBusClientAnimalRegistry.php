<?php

declare(strict_types=1);

namespace App\Context\Client\Infrastructure\Adapter\Animal;

use App\Context\Animal\Application\Command\CreateAnimal\CreateAnimal;
use App\Context\Client\Application\Command\CreateClient\NewAnimalDto;
use App\Context\Client\Application\Port\ClientAnimalRegistryInterface;
use App\Shared\Application\Bus\CommandBusInterface;

/**
 * Asks the Animal context to open the record of an animal the new client
 * comes with: alive, theirs alone, identified by its chip when it has one.
 */
final readonly class CommandBusClientAnimalRegistry implements ClientAnimalRegistryInterface
{
    public function __construct(private CommandBusInterface $commandBus)
    {
    }

    public function register(string $clinicId, string $ownerClientId, NewAnimalDto $animal): string
    {
        $animalId = $this->commandBus->dispatch(new CreateAnimal(
            clinicId: $clinicId,
            name: $animal->name,
            species: $animal->species,
            sex: $animal->sex,
            reproductiveStatus: $animal->reproductiveStatus,
            isMixedBreed: $animal->isMixedBreed,
            breedName: $animal->breedName,
            birthDate: $animal->birthDate,
            color: null,
            photoUrl: null,
            microchipNumber: $animal->microchipNumber,
            tattooNumber: null,
            passportNumber: null,
            registryType: 'none',
            registryNumber: null,
            registryReference: null,
            lifeStatus: 'alive',
            deceasedAt: null,
            missingSince: null,
            transferStatus: 'none',
            soldAt: null,
            givenAt: null,
            auxiliaryContactFirstName: null,
            auxiliaryContactLastName: null,
            auxiliaryContactPhoneNumber: null,
            primaryOwnerClientId: $ownerClientId,
            secondaryOwnerClientIds: [],
        ));
        \assert(\is_string($animalId));

        return $animalId;
    }
}
