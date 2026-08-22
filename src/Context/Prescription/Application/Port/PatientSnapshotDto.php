<?php

declare(strict_types=1);

namespace App\Context\Prescription\Application\Port;

final readonly class PatientSnapshotDto
{
    /**
     * @param list<string> $ownerAddressLines
     */
    public function __construct(
        public ?string $animalId,
        public string $animalName,
        public ?string $species = null,
        public ?string $breed = null,
        public ?\DateTimeImmutable $birthDate = null,
        public ?string $microchipNumber = null,
        public ?string $ownerClientId = null,
        public ?string $ownerName = null,
        public array $ownerAddressLines = [],
    ) {
    }
}
