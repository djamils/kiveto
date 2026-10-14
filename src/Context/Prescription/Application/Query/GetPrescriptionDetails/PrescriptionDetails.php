<?php

declare(strict_types=1);

namespace App\Context\Prescription\Application\Query\GetPrescriptionDetails;

/**
 * Everything the ordonnance page and the printable sheet need, in one read.
 */
final readonly class PrescriptionDetails
{
    /**
     * @param list<string>               $ownerAddressLines
     * @param list<PrescriptionLineView> $lines
     * @param list<RenewalView>          $renewals
     */
    public function __construct(
        public string $id,
        public string $clinicId,
        public string $consultationId,
        public ?string $number,
        public string $status,
        public string $statusLabel,
        public bool $isExpired,
        public bool $isDispensable,
        public string $prescriberName,
        public ?string $prescriberRegistrationNumber,
        public string $patientName,
        public ?string $patientSpecies,
        public ?string $patientBreed,
        public ?\DateTimeImmutable $patientBirthDate,
        public ?string $patientMicrochipNumber,
        public ?string $ownerClientId,
        public string $ownerName,
        public array $ownerAddressLines,
        public array $lines,
        public array $renewals,
        public int $renewalsAllowed,
        public int $renewalsUsed,
        public int $renewalsRemaining,
        public bool $carriesControlledSubstance,
        public int $validityDays,
        public ?\DateTimeImmutable $issuedAt,
        public ?\DateTimeImmutable $validUntil,
        public ?string $notes,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $cancelledAt,
        public ?string $cancellationReason,
        public string $prescriberId,
        public ?string $patientAnimalId,
        public ?string $patientWeightKg,
    ) {
    }
}
