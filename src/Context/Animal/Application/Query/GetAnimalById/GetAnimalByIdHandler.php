<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Query\GetAnimalById;

use App\Context\Animal\Application\Port\AnimalReadRepositoryInterface;
use App\Context\Animal\Domain\Exception\AnimalNotFoundException;
use App\Context\Animal\Domain\ValueObject\AnimalId;
use App\Context\Animal\Domain\ValueObject\ClinicId;
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
            throw new AnimalNotFoundException($query->animalId);
        }

        return $view;
    }
}
