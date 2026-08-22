<?php

declare(strict_types=1);

namespace App\Context\Prescription\Application\Query\SearchPrescriptions;

final readonly class PrescriptionListRow
{
    public function __construct(
        public string $id,
        public ?string $number,
        public string $status,
        public string $patientName,
        public ?string $patientSpecies,
        public string $ownerName,
        public string $prescriberName,
        public int $lineCount,
        public bool $carriesControlledSubstance,
        public int $renewalsRemaining,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $issuedAt,
        public ?\DateTimeImmutable $validUntil,
        public string $consultationId,
    ) {
    }

    public function isExpiredAt(\DateTimeImmutable $now): bool
    {
        return null !== $this->validUntil && $now > $this->validUntil;
    }
}
