<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetPortalRequest;

/**
 * A request as VetApp details it: for a pet change, the value at the
 * request, the pet's current value and the proposed one (« Valeur à la
 * demande / Valeur actuelle / Valeur proposée »; weights in grams). Owner
 * text (`proposedValue`, `note`) is rendered as text, never as HTML.
 */
final readonly class PortalRequestDetails
{
    /**
     * @param string                    $kind                 pet_change|prescription_renewal
     * @param ?string                   $field                pet changes: name|weight|sterilization|chip_number|other
     * @param array<string, string|int> $normalisedValue      pet changes: `{name}`, `{weightGrams, measuredOn}`, `{reproductiveStatus}`, `{chipNumber}` or `{}`
     * @param ?string                   $currentValueAtSubmit pet changes: the pet's value when asked (weight in grams)
     * @param ?string                   $currentValue         pet changes: the pet's value now (weight in grams)
     * @param ?string                   $petCondition         renewals: improved|no_improvement|adverse_effects
     * @param string                    $status               submitted|done|rejected|closed (a submitted request of an archived or deceased pet reads closed)
     * @param ?string                   $closedReason         link_revoked|client_archived|account_deleted|prescription_cancelled|pet_archived|pet_deceased
     * @param \DateTimeImmutable        $submittedAt          in the clinic's time zone
     * @param ?\DateTimeImmutable       $resolvedAt           in the clinic's time zone
     * @param bool                      $clientUnreachable    the answer could not be sent (« client injoignable »)
     */
    public function __construct(
        public string $id,
        public string $kind,
        public string $clientId,
        public ?string $clientName,
        public string $userId,
        public string $petId,
        public ?string $petName,
        public ?string $field,
        public ?string $proposedValue,
        public array $normalisedValue,
        public ?string $currentValueAtSubmit,
        public ?string $currentValue,
        public ?string $prescriptionId,
        public ?string $petCondition,
        public ?string $note,
        public string $status,
        public ?string $closedReason,
        public \DateTimeImmutable $submittedAt,
        public ?\DateTimeImmutable $resolvedAt,
        public ?string $resolvedByName,
        public ?string $resolutionMessage,
        public ?string $resultingPrescriptionId,
        public ?string $notificationMessageId,
        public bool $clientUnreachable,
    ) {
    }
}
