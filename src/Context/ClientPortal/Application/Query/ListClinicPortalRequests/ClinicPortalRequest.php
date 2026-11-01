<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListClinicPortalRequests;

/**
 * A request in the VetApp queue. `note`, `proposedValue` and the client's
 * names are owner text: rendered as text, never as HTML.
 */
final readonly class ClinicPortalRequest
{
    /**
     * @param string              $kind              pet_change|prescription_renewal
     * @param ?string             $clientName        null when the client is no longer known
     * @param ?string             $petName           null when the pet is no longer known
     * @param ?string             $field             pet changes: name|weight|sterilization|chip_number|other
     * @param ?string             $petCondition      renewals: improved|no_improvement|adverse_effects
     * @param string              $status            submitted|done|rejected
     * @param \DateTimeImmutable  $submittedAt       in the clinic's time zone
     * @param ?\DateTimeImmutable $resolvedAt        in the clinic's time zone
     * @param bool                $clientUnreachable the answer could not be sent (« client injoignable »)
     */
    public function __construct(
        public string $id,
        public string $kind,
        public string $clientId,
        public ?string $clientName,
        public string $petId,
        public ?string $petName,
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
    ) {
    }
}
