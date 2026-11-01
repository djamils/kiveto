<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\View;

use ApiPlatform\Metadata\ApiProperty;
use App\Context\ClientPortal\Application\Query\ListOwnerBookingRequests\OwnerBookingRequest;
use App\Presentation\Api\Portal\Support\ApiDate;

/**
 * A new-client booking request (S7d), account-scoped: never the clinic id
 * nor the appointment before it is confirmed.
 */
final readonly class BookingRequestView
{
    public function __construct(
        public string $id,
        public ClinicSummaryView $clinic,
        public BookingRequestPetView $pet,
        /** The motif's label. */
        public string $motif,
        /** With the clinic's offset. */
        #[ApiProperty(schema: ['type' => 'string', 'format' => 'date-time'])]
        public string $startAt,
        #[ApiProperty(schema: ['type' => 'string', 'enum' => ['pending', 'confirmed', 'declined']])]
        public string $status,
        /** When declined: by the clinic, expired unanswered, slot cancelled by the clinic, account deleted. */
        #[ApiProperty(schema: ['type' => ['string', 'null'], 'enum' => ['declined', 'expired', 'cancelled', 'account_deleted', null]])]
        public ?string $declineReason,
        /** When confirmed: the appointment, now under `/clinics/{c}/appointments`. */
        #[ApiProperty(schema: ['type' => ['string', 'null'], 'format' => 'uuid'])]
        public ?string $appointmentId,
    ) {
    }

    public static function of(OwnerBookingRequest $request): self
    {
        return new self(
            $request->id,
            new ClinicSummaryView($request->clinicName, $request->clinicCity),
            new BookingRequestPetView($request->petName, $request->petSpecies),
            $request->motif,
            ApiDate::formatLocal($request->startAt),
            $request->status,
            $request->declineReason,
            $request->appointmentId,
        );
    }
}
