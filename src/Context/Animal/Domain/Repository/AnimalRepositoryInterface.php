<?php

declare(strict_types=1);

namespace App\Context\Animal\Domain\Repository;

use App\Context\Animal\Domain\Animal;
use App\Context\Animal\Domain\Exception\AnimalNotFoundException;
use App\Context\Animal\Domain\ValueObject\AnimalId;
use App\Context\Clinic\Domain\ValueObject\ClinicId;

interface AnimalRepositoryInterface
{
    public function save(Animal $animal): void;

    /**
     * @throws AnimalNotFoundException
     */
    public function get(ClinicId $clinicId, AnimalId $animalId): Animal;

    public function findById(ClinicId $clinicId, AnimalId $animalId): ?Animal;

    public function existsByMicrochip(
        ClinicId $clinicId,
        string $microchipNumber,
        ?AnimalId $exceptAnimalId = null,
    ): bool;

    /**
     * @return list<Animal>
     */
    public function findByActiveOwner(ClinicId $clinicId, string $clientId): array;
}
