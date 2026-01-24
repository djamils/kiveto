<?php

declare(strict_types=1);

namespace App\Animal\Application\Query\SearchAnimals;

use App\Animal\Domain\Port\AnimalReadRepositoryInterface;
use App\Clinic\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

// QueryHandlerInterface removed - Symfony handles it via AsMessageHandler

#[AsMessageHandler]
final readonly class SearchAnimalsHandler
{
    public function __construct(
        private AnimalReadRepositoryInterface $readRepository,
    ) {
    }

    /**
     * @return array{items: list<AnimalListItemView>, total: int}
     */
    public function __invoke(SearchAnimals $query): array
    {
        $clinicId = ClinicId::fromString($query->clinicId);

        $criteria = new SearchAnimalsCriteria(
            searchTerm: $query->searchTerm,
            status: $query->status,
            species: $query->species,
            lifeStatus: $query->lifeStatus,
            ownerClientId: $query->ownerClientId,
            page: $query->page,
            limit: $query->limit,
        );

        return $this->readRepository->search($clinicId, $criteria);
    }
}
