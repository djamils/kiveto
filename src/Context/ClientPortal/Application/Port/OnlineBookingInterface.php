<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/**
 * Online booking (Scheduling context). Every method answers the
 * appointment id; Scheduling's refusals (`booking_not_open`, `not_found`,
 * `slot_taken`, `cancellation_window_closed`, `appointment_not_modifiable`)
 * pass through as they are, for the API to map.
 */
interface OnlineBookingInterface
{
    /**
     * @param string  $ownerId      the client the appointment is for
     * @param ?string $createdPetId the animal registered for this booking
     */
    public function book(
        string $clinicId,
        string $motifId,
        string $practitionerId,
        \DateTimeImmutable $startsAt,
        string $ownerId,
        string $animalId,
        ?string $createdPetId = null,
    ): string;

    public function reschedule(string $clinicId, string $appointmentId, string $practitionerId, \DateTimeImmutable $startsAt): string;

    /**
     * @param ?string $reason unavailable|no_longer_needed|seen_elsewhere|other
     */
    public function cancel(string $clinicId, string $appointmentId, ?string $reason): string;
}
