<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Port;

/**
 * Every confirmation of a new client's request (portal, pending, no client
 * file yet) goes through ClientPortal's `ConfirmBookingRequest` (S7-01): the
 * client file (the one sibling request was confirmed for, else the one
 * non-archived file of the clinic whose mobile is the account's phone, else
 * a new file from the Portal profile), the animal (the appointment's own
 * when it belongs to that file, else the requested new pet), the Portal link
 * confirmed, then `ConfirmNewClientAppointment`. The agenda's « Confirmer »,
 * a check-in, a consultation start and a completion call it, before taking
 * any Scheduling lock (ClientPortal locks its request first).
 */
interface NewClientConfirmationInterface
{
    /**
     * @param ?string $animalId the appointment's animal, kept when it belongs to the client file
     * @param bool    $silent   the client is on site: no message, the expiry does not apply
     *
     * @return bool false when no pending booking request holds this appointment (nothing changed)
     */
    public function confirm(string $clinicId, string $appointmentId, ?string $animalId, bool $silent): bool;
}
