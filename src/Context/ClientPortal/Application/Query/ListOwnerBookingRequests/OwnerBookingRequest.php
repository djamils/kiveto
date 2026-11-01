<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListOwnerBookingRequests;

use App\Context\ClientPortal\Application\Port\ClinicLabel;
use App\Context\ClientPortal\Domain\Booking\BookingRequest;
use App\Context\ClientPortal\Domain\Booking\BookingRequestStatus;

/** A booking request as its owner sees it. */
final readonly class OwnerBookingRequest
{
    /**
     * @param ?string            $petName       null once erased
     * @param ?string            $petSpecies    dog|cat|nac|other; null once erased
     * @param \DateTimeImmutable $startAt       in the clinic's time zone
     * @param string             $status        pending|confirmed|declined
     * @param ?string            $declineReason declined|expired|cancelled|account_deleted, when declined
     * @param ?string            $appointmentId when confirmed: the appointment under `/clinics/{c}/appointments`
     */
    public function __construct(
        public string $id,
        public string $clinicName,
        public ?string $clinicCity,
        public ?string $petName,
        public ?string $petSpecies,
        public string $motif,
        public \DateTimeImmutable $startAt,
        public string $status,
        public ?string $declineReason,
        public ?string $appointmentId,
    ) {
    }

    public static function of(BookingRequest $request, ClinicLabel $clinic): self
    {
        return new self(
            $request->id()->toString(),
            $clinic->name,
            $clinic->city,
            $request->newPetName(),
            $request->newPetSpecies(),
            $request->motifLabel(),
            $request->startAt()->setTimezone(new \DateTimeZone($clinic->timeZone)),
            $request->status()->value,
            $request->declineReason()?->value,
            BookingRequestStatus::CONFIRMED === $request->status() ? $request->appointmentId() : null,
        );
    }
}
