<?php

declare(strict_types=1);

namespace App\Animal\Application\Port;

use App\Animal\Application\Query\GetAnimalById\AnimalView;
use App\Animal\Application\Query\SearchAnimals\AnimalListItemView;
use App\Animal\Application\Query\SearchAnimals\SearchAnimalsCriteria;
use App\Animal\Domain\ValueObject\AnimalId;
use App\Clinic\Domain\ValueObject\ClinicId;

interface AnimalReadRepositoryInterface
{
    public function findById(ClinicId $clinicId, AnimalId $animalId): ?AnimalView;

    /**
     * @return array{items: list<AnimalListItemView>, total: int}
     */
    public function search(ClinicId $clinicId, SearchAnimalsCriteria $criteria): array;
}
