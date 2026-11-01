<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/**
 * A pet change or renewal request as the lists read it; the fields of the
 * other kind are null. Instants are UTC.
 */
final readonly class PortalRequestRecord
{
    /**
     * @param string  $kind         pet_change|prescription_renewal
     * @param ?string $field        pet changes: name|weight|sterilization|chip_number|other
     * @param ?string $petCondition renewals: improved|no_improvement|adverse_effects
     * @param string  $status       submitted|done|rejected|closed
     */
    public function __construct(
        public string $id,
        public string $kind,
        public string $clinicId,
        public string $clientId,
        public string $userId,
        public string $petId,
        public ?string $field,
        public ?string $proposedValue,
        public ?string $prescriptionId,
        public ?string $petCondition,
        public ?string $note,
        public string $status,
        public \DateTimeImmutable $submittedAt,
        public ?\DateTimeImmutable $resolvedAt,
        public ?string $resolvedByName,
        public ?string $resolutionMessage,
        public ?string $resultingPrescriptionId,
        public bool $clientUnreachable,
        public ?string $closedReason,
    ) {
    }
}
