<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Search;

interface AnimalSearchIndexWriterInterface
{
    public function upsert(AnimalSearchIndexData $data): void;

    public function updateName(string $animalId, string $clinicId, string $newName): void;

    public function updateChip(string $animalId, string $clinicId, ?string $chip): void;

    public function updateOwner(
        string $animalId,
        string $clinicId,
        ?string $ownerClientId,
        ?string $ownerName,
        ?string $ownerPhone = null,
    ): void;

    public function updateOwnerName(string $clientId, string $clinicId, string $newOwnerName): void;

    public function markArchived(string $animalId, string $clinicId): void;

    public function delete(string $animalId, string $clinicId): void;
}
