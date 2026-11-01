<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Adapter\Animal;

use App\Context\Animal\Application\Command\ArchiveAnimal\ArchiveAnimal;
use App\Context\Animal\Application\Query\GetAnimalById\AnimalView;
use App\Context\Animal\Application\Query\GetAnimalById\GetAnimalById;
use App\Context\Patient\Application\Query\HasPatientForAnimal\HasPatientForAnimal;
use App\Context\Scheduling\Application\Port\BookedPetArchiverInterface;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;

/**
 * Animal's GetAnimalById (still active?), Patient's HasPatientForAnimal (a
 * clinical record?), then Animal's ArchiveAnimal.
 */
final readonly class CommandBusBookedPetArchiver implements BookedPetArchiverInterface
{
    private const string ACTIVE = 'active';

    public function __construct(
        private QueryBusInterface $queryBus,
        private CommandBusInterface $commandBus,
    ) {
    }

    public function archiveUnlessRecorded(string $clinicId, string $animalId): void
    {
        try {
            $animal = $this->queryBus->ask(new GetAnimalById($clinicId, $animalId));
        } catch (\DomainException) {
            // Unknown animal: nothing to archive
            return;
        }

        if (!$animal instanceof AnimalView || self::ACTIVE !== $animal->status) {
            return;
        }

        if (true === $this->queryBus->ask(new HasPatientForAnimal($clinicId, $animalId))) {
            return;
        }

        $this->commandBus->dispatch(new ArchiveAnimal($clinicId, $animalId));
    }
}
