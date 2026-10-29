<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Adapter\Animal;

use App\Context\Animal\Application\Query\GetAnimalById\AnimalView;
use App\Context\Animal\Application\Query\GetAnimalById\GetAnimalById;
use App\Context\Scheduling\Application\Port\AnimalDirectoryInterface;
use App\Shared\Application\Bus\QueryBusInterface;

/**
 * Animal's GetAnimalById, which refuses (a LogicException) an animal of another clinic or an
 * unknown one (read as no name).
 */
final readonly class QueryBusAnimalDirectory implements AnimalDirectoryInterface
{
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function nameOf(string $clinicId, string $animalId): ?string
    {
        try {
            $animal = $this->queryBus->ask(new GetAnimalById($clinicId, $animalId));
        } catch (\LogicException) {
            return null;
        }

        return $animal instanceof AnimalView ? $animal->name : null;
    }
}
