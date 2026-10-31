<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

final readonly class PrescriptionPublication
{
    public function __construct(
        public string $prescriptionId,
        public string $ownerClientId,
        public string $number,
        public \DateTimeImmutable $issuedAt,
        public string $consultationId,
        public ?string $animalId,
        public string $prescriberName,
    ) {
    }
}
