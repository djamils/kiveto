<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Notification;

use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;

/**
 * Notification states are read with a row lock held until the end of the
 * transaction (`SELECT … FOR UPDATE`): concurrent syncs and reminder
 * requests of one appointment run one after the other, each seeing what the
 * previous one committed.
 */
interface AppointmentNotificationStateRepositoryInterface
{
    /**
     * The state of an appointment, created first when absent, locked; null
     * when the appointment does not exist.
     */
    public function lock(AppointmentId $appointmentId): ?AppointmentNotificationState;

    /** The state of an appointment, locked; null when it has none. */
    public function findForUpdate(AppointmentId $appointmentId): ?AppointmentNotificationState;

    public function save(AppointmentNotificationState $state): void;

    /**
     * Marks an appointment's cancellation as never to be told (created when
     * absent), within the transaction of the cancellation.
     */
    public function silence(AppointmentId $appointmentId, ClinicId $clinicId): void;

    /**
     * The appointments whose reminder is due and not requested yet.
     *
     * @return list<string> appointment ids
     */
    public function dueReminders(\DateTimeImmutable $now): array;

    /**
     * The reminders of a clinic not requested yet, for appointments still
     * planned and to come, locked.
     *
     * @return list<PendingReminder>
     */
    public function lockPendingReminders(ClinicId $clinicId, \DateTimeImmutable $now): array;
}
