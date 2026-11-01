<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Adapter\Animal;

use App\Context\Animal\Application\Query\GetAnimalById\AnimalView;
use App\Context\Animal\Application\Query\GetAnimalById\GetAnimalById;
use App\Context\Animal\Application\Query\ListPortalPets\ListPortalPets;
use App\Context\Animal\Application\Query\ListPortalPets\PortalPetView;
use App\Context\ClientPortal\Application\Port\PetDirectoryInterface;
use App\Context\ClientPortal\Application\Port\PetIdentity;
use App\Context\ClientPortal\Application\Port\PetState;
use App\Context\ClientPortal\Application\Port\PortalPet;
use App\Shared\Application\Bus\QueryBusInterface;

/**
 * Animal's ListPortalPets: the visibility rule (active, primary owner, not
 * transferred) is Animal's. GetAnimalById for the identity of one animal
 * and for the files of the few pets a page of requests names.
 */
final readonly class QueryBusPetDirectory implements PetDirectoryInterface
{
    private const string ARCHIVED = 'archived';

    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function visiblePets(string $clinicId, array $clientIds): array
    {
        if ([] === $clientIds) {
            return [];
        }

        $views = $this->queryBus->ask(new ListPortalPets($clinicId, $clientIds));

        if (!\is_array($views)) {
            return [];
        }

        $pets = [];

        foreach ($views as $view) {
            \assert($view instanceof PortalPetView);
            $pets[] = new PortalPet(
                $view->id,
                $view->primaryOwnerClientId,
                $view->name,
                $view->species,
                $view->breedName,
                $view->isMixedBreed,
                $view->sex,
                $view->reproductiveStatus,
                $view->birthDate,
                $view->lifeStatus,
                $view->microchipNumber,
            );
        }

        return $pets;
    }

    public function identityOf(string $clinicId, string $animalId): ?PetIdentity
    {
        $animal = $this->animal($clinicId, $animalId);

        if (null === $animal) {
            return null;
        }

        return new PetIdentity(
            $animal->name,
            $animal->species,
            $animal->breedName,
            $animal->isMixedBreed,
            $animal->sex,
            null === $animal->birthDate ? null : new \DateTimeImmutable($animal->birthDate),
            $animal->identification->microchipNumber,
        );
    }

    public function statesOf(string $clinicId, array $animalIds): array
    {
        $states = [];

        foreach (array_unique($animalIds) as $animalId) {
            $animal = $this->animal($clinicId, $animalId);

            if (null !== $animal) {
                $states[$animalId] = new PetState(
                    $animal->id,
                    $animal->name,
                    $animal->primaryOwnerClientId(),
                    $animal->reproductiveStatus,
                    $animal->identification->microchipNumber,
                    $animal->lifeCycle->lifeStatus,
                    self::ARCHIVED === $animal->status,
                );
            }
        }

        return $states;
    }

    private function animal(string $clinicId, string $animalId): ?AnimalView
    {
        try {
            $animal = $this->queryBus->ask(new GetAnimalById($clinicId, $animalId));
        } catch (\DomainException) {
            // Animal's "not found": an unknown animal, or one of another clinic.
            return null;
        }

        return $animal instanceof AnimalView ? $animal : null;
    }
}
