<?php

declare(strict_types=1);

namespace App\Animal\Application\Query\GetAnimalById;

use App\Animal\Application\Port\AnimalReadRepositoryInterface;
use App\Animal\Domain\Exception\AnimalNotFound;
use App\Animal\Domain\ValueObject\AnimalId;
use App\Clinic\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

// QueryHandlerInterface removed - Symfony handles it via AsMessageHandler

#[AsMessageHandler]
final readonly class GetAnimalByIdHandler
{
    public function __construct(
        private AnimalReadRepositoryInterface $readRepository,
    ) {
    }

    public function __invoke(GetAnimalById $query): AnimalView
    {
        $clinicId = ClinicId::fromString($query->clinicId);
        $animalId = AnimalId::fromString($query->animalId);

        $view = $this->readRepository->findById($clinicId, $animalId);

        if (null === $view) {
            throw AnimalNotFound::withId($query->animalId);
        }

        return $view;
    }
}
