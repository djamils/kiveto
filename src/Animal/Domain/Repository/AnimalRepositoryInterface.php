<?php

declare(strict_types=1);

namespace App\Animal\Domain\Repository;

use App\Animal\Domain\Animal;
use App\Animal\Domain\Exception\AnimalNotFound;
use App\Animal\Domain\ValueObject\AnimalId;
use App\Clinic\Domain\ValueObject\ClinicId;

interface AnimalRepositoryInterface
{
    public function save(Animal $animal): void;

    /**
     * @throws AnimalNotFound
     */
    public function get(ClinicId $clinicId, AnimalId $animalId): Animal;

    public function find(ClinicId $clinicId, AnimalId $animalId): ?Animal;

    public function nextId(): AnimalId;

    public function existsMicrochip(
        ClinicId $clinicId,
        string $microchipNumber,
        ?AnimalId $exceptAnimalId = null,
    ): bool;

    /**
     * @return list<Animal>
     */
    public function findByActiveOwner(ClinicId $clinicId, string $clientId): array;
}
