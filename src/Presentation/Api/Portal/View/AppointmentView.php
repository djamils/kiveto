<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\View;

use ApiPlatform\Metadata\ApiProperty;
use App\Context\ClientPortal\Application\Query\ListOwnerAppointments\PortalAppointment;
use App\Presentation\Api\Portal\Support\ApiDate;

/**
 * An appointment of the owner (slice 4): instants with the clinic's offset,
 * never the free-text reason nor the notes. `pet` is null for an appointment
 * without animal or of an animal the account does not see.
 */
final readonly class AppointmentView
{
    public const array STATUSES = ['confirmed', 'pending_clinic_confirmation', 'cancelled_by_client', 'cancelled_by_clinic', 'declined', 'completed', 'no_show'];

    public function __construct(
        public string $id,
        #[ApiProperty(schema: ['type' => 'string', 'format' => 'date-time'])]
        public string $startAt,
        #[ApiProperty(schema: ['type' => 'string', 'format' => 'date-time'])]
        public string $endAt,
        public ?PetReferenceView $pet,
        public MotifReferenceView $motif,
        public PractitionerView $practitioner,
        #[ApiProperty(schema: ['type' => 'string', 'enum' => self::STATUSES])]
        public string $status,
        #[ApiProperty(schema: ['type' => 'string', 'enum' => ['clinic', 'portal']])]
        public string $source,
        #[ApiProperty(schema: ['type' => ['string', 'null'], 'format' => 'date-time'])]
        public ?string $cancellableUntil,
        #[ApiProperty(schema: ['type' => 'string', 'enum' => ['not_started', 'submitted']])]
        public string $questionnaireStatus,
        public ?RelatedCareItemView $relatedCareItem,
    ) {
    }

    public static function of(PortalAppointment $appointment): self
    {
        return new self(
            $appointment->id,
            ApiDate::formatLocal($appointment->startAt),
            ApiDate::formatLocal($appointment->endAt),
            null === $appointment->petId || null === $appointment->petName ? null : new PetReferenceView($appointment->petId, $appointment->petName),
            new MotifReferenceView($appointment->motifId, $appointment->motifLabel),
            new PractitionerView($appointment->practitionerId, $appointment->practitionerName),
            $appointment->status,
            $appointment->source,
            null === $appointment->cancellableUntil ? null : ApiDate::formatLocal($appointment->cancellableUntil),
            $appointment->questionnaireStatus,
            null === $appointment->relatedCareItem ? null : RelatedCareItemView::of($appointment->relatedCareItem),
        );
    }
}
