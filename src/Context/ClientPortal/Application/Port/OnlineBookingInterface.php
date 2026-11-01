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

    /**
     * A new client's request through the directory (slice 7): no owner nor
     * animal, always pending, the manual lead time; answers the appointment id.
     */
    public function requestNewClientSlot(string $clinicId, string $motifId, string $practitionerId, \DateTimeImmutable $startsAt): string;

    /** The new client's request confirmed for the client file and animal staff chose. */
    public function confirmNewClient(string $clinicId, string $appointmentId, string $ownerId, string $animalId): void;

    /** The clinic declines a pending request (the slot is freed). */
    public function decline(string $clinicId, string $appointmentId): void;

    /** A pending request withdrawn whatever the cancellation window; anything else is left as it is. */
    public function withdrawPending(string $clinicId, string $appointmentId): void;
}
