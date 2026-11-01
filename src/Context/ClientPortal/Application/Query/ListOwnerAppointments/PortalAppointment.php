<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListOwnerAppointments;

use App\Context\ClientPortal\Application\Query\ListOwnerCareItems\OwnerCareItem;

/**
 * An appointment of the owner account, never its free-text reason or notes.
 */
final readonly class PortalAppointment
{
    public const string NO_MOTIF_LABEL = 'Rendez-vous';

    public const string QUESTIONNAIRE_NOT_STARTED = 'not_started';

    public const string QUESTIONNAIRE_SUBMITTED = 'submitted';

    /**
     * @param \DateTimeImmutable  $startAt             in the clinic's time zone
     * @param \DateTimeImmutable  $endAt               in the clinic's time zone
     * @param ?string             $petId               null without animal, or for an animal the account does not see
     * @param ?string             $motifId             null for an appointment taken without online motif (label « Rendez-vous »)
     * @param ?string             $practitionerName    the practitioner's display name, when known
     * @param string              $status              confirmed|pending_clinic_confirmation|cancelled_by_client|cancelled_by_clinic|declined|completed|no_show
     * @param string              $source              clinic|portal
     * @param ?\DateTimeImmutable $cancellableUntil    in the clinic's time zone; null once the slot is no longer held or the start is past
     * @param string              $questionnaireStatus not_started|submitted (the pre-visit questionnaire, closed ones read not_started)
     * @param ?OwnerCareItem      $relatedCareItem     the pet's care item of the motif's kind
     */
    public function __construct(
        public string $id,
        public \DateTimeImmutable $startAt,
        public \DateTimeImmutable $endAt,
        public ?string $petId,
        public ?string $petName,
        public ?string $motifId,
        public string $motifLabel,
        public string $practitionerId,
        public ?string $practitionerName,
        public string $status,
        public bool $occupiesSlot,
        public bool $isPending,
        public string $source,
        public ?\DateTimeImmutable $cancellableUntil,
        public string $questionnaireStatus,
        public ?OwnerCareItem $relatedCareItem,
    ) {
    }
}
