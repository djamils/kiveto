<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Port;

use App\Context\Animal\Application\Query\FindByMicrochip\AnimalMicrochipView;
use App\Context\Animal\Application\Query\GetAnimalById\AnimalView;
use App\Context\Animal\Application\Query\ListAnimalSummariesPerClientIds\AnimalSummary;
use App\Context\Animal\Application\Query\SearchAnimals\AnimalListItemView;
use App\Context\Animal\Application\Query\SearchAnimals\SearchAnimalsCriteria;
use App\Context\Animal\Domain\ValueObject\AnimalId;
use App\Context\Animal\Domain\ValueObject\ClinicId;

interface AnimalReadRepositoryInterface
{
    public function findById(ClinicId $clinicId, AnimalId $animalId): ?AnimalView;

    /**
     * @return array{items: list<AnimalListItemView>, total: int}
     */
    public function search(ClinicId $clinicId, SearchAnimalsCriteria $criteria): array;

    public function countBy(ClinicId $clinicId, SearchAnimalsCriteria $criteria): int;

    /**
     * Identifiers of the animals matching a free-text term and/or a species.
     *
     * Unpaginated on purpose: callers use it to narrow a paginated query they
     * own, so a truncated list would silently drop results.
     *
     * @return list<string>
     */
    public function findIdsMatching(ClinicId $clinicId, ?string $searchTerm, ?string $species): array;

    /**
     * @param list<string> $clientIds UUID strings
     *
     * @return array<string, list<AnimalSummary>> clientId => summaries (capped at $limit per client, alphabetical)
     */
    public function listAnimalSummariesByPrimaryOwnerClientIds(
        ClinicId $clinicId,
        array $clientIds,
        int $limit,
    ): array;

    public function findByMicrochip(string $microchipNumber, string $clinicId): ?AnimalMicrochipView;
}
