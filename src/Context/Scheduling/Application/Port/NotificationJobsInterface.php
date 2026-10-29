<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Port;

/**
 * Queues the asynchronous work of appointment messages: the debounced sync
 * of an appointment (D2), the reminder request of a due appointment.
 */
interface NotificationJobsInterface
{
    /** SyncAppointmentNotification, run after the debounce delay (2 minutes). */
    public function syncLater(string $appointmentId): void;

    /** RequestAppointmentReminder, run by a worker as soon as possible. */
    public function requestReminder(string $appointmentId): void;

    /** PostNewAppointmentNotice, run by a worker as soon as possible. */
    public function postNewAppointmentNotice(string $appointmentId, ?string $actorUserId): void;

    /** WithdrawNewAppointmentNotice, run by a worker as soon as possible. */
    public function withdrawNewAppointmentNotice(string $appointmentId): void;
}
