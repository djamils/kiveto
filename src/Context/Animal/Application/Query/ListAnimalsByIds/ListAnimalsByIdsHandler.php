<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Query\ListAnimalsByIds;

use App\Context\Animal\Application\Port\AnimalReadRepositoryInterface;
use App\Context\Animal\Application\Query\GetAnimalById\AnimalView;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The animals of the clinic among the given ids, by id; an unknown id, or
 * one of another clinic, is left out (where GetAnimalById would refuse it).
 */
#[AsMessageHandler]
final readonly class ListAnimalsByIdsHandler
{
    public function __construct(
        private AnimalReadRepositoryInterface $readRepository,
    ) {
    }

    /**
     * @return array<string, AnimalView> by animal id
     */
    public function __invoke(ListAnimalsByIds $query): array
    {
        $animalIds = array_values(array_unique($query->animalIds));

        if ([] === $animalIds) {
            return [];
        }

        $animals = [];

        foreach ($this->readRepository->findByIds(ClinicId::fromString($query->clinicId), $animalIds) as $animal) {
            $animals[$animal->id] = $animal;
        }

        return $animals;
    }
}
