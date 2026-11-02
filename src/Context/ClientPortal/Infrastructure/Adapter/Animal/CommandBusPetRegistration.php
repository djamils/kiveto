<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Adapter\Animal;

use App\Context\Animal\Application\Command\CreateAnimal\CreateAnimal;
use App\Context\Animal\Application\Query\FindRecentPetOfOwner\FindRecentPetOfOwner;
use App\Context\ClientPortal\Application\Port\PetRegistrationInterface;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;

/**
 * Animal's FindRecentPetOfOwner and CreateAnimal with the defaults of S4g:
 * sex and reproductive status unknown, not mixed breed, no registry, alive,
 * not transferred, every other field empty, the client as sole owner.
 */
final readonly class CommandBusPetRegistration implements PetRegistrationInterface
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private QueryBusInterface $queryBus,
    ) {
    }

    public function recentPetOf(string $clinicId, string $animalId, string $ownerClientId, string $name, string $species, \DateTimeImmutable $since): ?string
    {
        $id = $this->queryBus->ask(new FindRecentPetOfOwner($clinicId, $animalId, $ownerClientId, $name, $species, $since));

        return \is_string($id) ? $id : null;
    }

    public function register(string $clinicId, string $ownerClientId, string $name, string $species): string
    {
        $animalId = $this->commandBus->dispatch(new CreateAnimal(
            clinicId: $clinicId,
            name: $name,
            species: $species,
            sex: 'unknown',
            reproductiveStatus: 'unknown',
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
