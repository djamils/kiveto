<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Port;

/**
 * The in-app bell of the clinic's members (Notification's inbox).
 */
interface PractitionerBellInterface
{
    /**
     * Posts « Nouveau RDV » to the appointment's veterinarian; the entry opens
     * the agenda on the appointment's day, the appointment selected.
     *
     * @param string $localDate the appointment's day in the clinic time zone (Y-m-d)
     */
    public function announceNewAppointment(
        string $clinicId,
        string $practitionerUserId,
        string $appointmentId,
        string $localDate,
        string $title,
        string $detail,
        string $idempotencyKey,
    ): void;

    /**
     * Posts « RDV en ligne à confirmer » to the appointment's practitioner;
     * the entry opens the agenda on the appointment's day, the appointment
     * selected.
     *
     * @param string $localDate the appointment's day in the clinic time zone (Y-m-d)
     */
    public function announcePendingBooking(
        string $clinicId,
        string $practitionerUserId,
        string $appointmentId,
        string $localDate,
        string $title,
        string $detail,
        string $idempotencyKey,
    ): void;

    /**
     * Posts « RDV modifié en ligne » / « RDV annulé en ligne » to the
     * practitioners concerned by a client's cancel or move on the Portal (the
     * old and the new one, once each).
     *
     * @param list<string> $practitionerUserIds
     * @param string       $localDate           the appointment's day in the clinic time zone (Y-m-d)
     */
    public function announceOnlineChange(
        string $clinicId,
        array $practitionerUserIds,
        string $appointmentId,
        string $localDate,
        string $title,
        string $detail,
        string $idempotencyKey,
    ): void;

    /** Removes the entries posted under that key that are still unread. */
    public function withdraw(string $idempotencyKey): void;
}
