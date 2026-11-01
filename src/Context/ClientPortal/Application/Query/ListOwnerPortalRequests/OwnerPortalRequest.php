<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListOwnerPortalRequests;

final readonly class OwnerPortalRequest
{
    /**
     * @param string              $kind        pet_change|prescription_renewal
     * @param ?string             $field       pet changes: name|weight|sterilization|chip_number|other
     * @param string              $status      submitted|done|rejected|closed (a submitted request of an archived or deceased pet reads closed)
     * @param \DateTimeImmutable  $submittedAt in the clinic's time zone
     * @param ?\DateTimeImmutable $resolvedAt  in the clinic's time zone; null until answered
     */
    public function __construct(
        public string $id,
        public string $kind,
        public string $petId,
        public ?string $field,
        public ?string $proposedValue,
        public ?string $prescriptionId,
        public string $status,
        public \DateTimeImmutable $submittedAt,
        public ?\DateTimeImmutable $resolvedAt,
        public ?string $resolutionMessage,
        public ?string $resultingPrescriptionId,
    ) {
    }
}
