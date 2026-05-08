<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Query\ListAnimalSummariesPerClientIds;

use App\Context\Animal\Application\Port\AnimalReadRepositoryInterface;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListAnimalSummariesPerClientIdsHandler
{
    public function __construct(
        private AnimalReadRepositoryInterface $readRepository,
    ) {
    }

    /**
     * @return array<string, list<AnimalSummary>>
     */
    public function __invoke(ListAnimalSummariesPerClientIds $query): array
    {
        if ([] === $query->clientIds) {
            return [];
        }

        return $this->readRepository->listAnimalSummariesByPrimaryOwnerClientIds(
            ClinicId::fromString($query->clinicId),
            $query->clientIds,
            $query->limit,
        );
    }
}
