<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Port;

use App\Context\Animal\Application\Query\FindByMicrochip\AnimalMicrochipView;
use App\Context\Animal\Application\Query\GetAnimalById\AnimalView;
use App\Context\Animal\Application\Query\ListAnimalSummariesPerClientIds\AnimalSummary;
use App\Context\Animal\Application\Query\SearchAnimals\AnimalListItemView;
use App\Context\Animal\Application\Query\SearchAnimals\SearchAnimalsCriteria;
use App\Context\Animal\Domain\ValueObject\AnimalId;
use App\Context\Clinic\Domain\ValueObject\ClinicId;

interface AnimalReadRepositoryInterface
{
    public function findById(ClinicId $clinicId, AnimalId $animalId): ?AnimalView;

    /**
     * @return array{items: list<AnimalListItemView>, total: int}
     */
    public function search(ClinicId $clinicId, SearchAnimalsCriteria $criteria): array;

    public function countBy(ClinicId $clinicId, SearchAnimalsCriteria $criteria): int;

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
