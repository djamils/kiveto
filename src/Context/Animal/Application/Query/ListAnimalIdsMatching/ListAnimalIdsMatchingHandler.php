<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Query\ListAnimalIdsMatching;

use App\Context\Animal\Application\Port\AnimalReadRepositoryInterface;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListAnimalIdsMatchingHandler
{
    public function __construct(
        private AnimalReadRepositoryInterface $readRepository,
    ) {
    }

    /**
     * @return list<string>
     */
    public function __invoke(ListAnimalIdsMatching $query): array
    {
        return $this->readRepository->findIdsMatching(
            ClinicId::fromString($query->clinicId),
            $query->searchTerm,
            $query->species,
        );
    }
}
