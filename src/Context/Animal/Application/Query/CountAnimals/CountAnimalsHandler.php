<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Query\CountAnimals;

use App\Context\Animal\Application\Port\AnimalReadRepositoryInterface;
use App\Context\Animal\Application\Query\SearchAnimals\SearchAnimalsCriteria;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CountAnimalsHandler
{
    public function __construct(
        private AnimalReadRepositoryInterface $readRepository,
    ) {
    }

    public function __invoke(CountAnimals $query): int
    {
        $clinicId = ClinicId::fromString($query->clinicId);

        $criteria = new SearchAnimalsCriteria(
            searchTerm: $query->searchTerm,
            status: $query->status,
            species: $query->species,
            lifeStatus: $query->lifeStatus,
            ownerClientId: $query->ownerClientId,
        );

        return $this->readRepository->countBy($clinicId, $criteria);
    }
}
