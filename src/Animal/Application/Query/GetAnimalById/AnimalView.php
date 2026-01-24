<?php

declare(strict_types=1);

namespace App\Animal\Application\Query\GetAnimalById;

final readonly class AnimalView
{
    /**
     * @param list<OwnershipDto> $ownerships
     */
    public function __construct(
        public string $id,
        public string $clinicId,
        public string $name,
        public string $species,
        public string $sex,
        public string $reproductiveStatus,
        public bool $isMixedBreed,
        public ?string $breedName,
        public ?string $birthDate,
        public ?string $color,
        public ?string $photoUrl,
        public IdentificationDto $identification,
        public LifeCycleDto $lifeCycle,
        public TransferDto $transfer,
        public ?AuxiliaryContactDto $auxiliaryContact,
        public string $status,
        public array $ownerships,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public function primaryOwnerClientId(): ?string
    {
        foreach ($this->ownerships as $ownership) {
            if ('primary' === $ownership->role && 'active' === $ownership->status) {
                return $ownership->clientId;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function secondaryOwnerClientIds(): array
    {
        $ids = [];
        foreach ($this->ownerships as $ownership) {
            if ('secondary' === $ownership->role && 'active' === $ownership->status) {
                $ids[] = $ownership->clientId;
            }
        }

        return $ids;
    }
}
