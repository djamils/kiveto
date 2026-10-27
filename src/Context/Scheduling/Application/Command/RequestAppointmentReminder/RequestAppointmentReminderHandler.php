<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\RequestAppointmentReminder;

use App\Context\Scheduling\Application\Notification\AppointmentMessageComposer;
use App\Context\Scheduling\Application\Port\ClientContact;
use App\Context\Scheduling\Application\Port\ClientContactInterface;
use App\Context\Scheduling\Application\Port\ClinicDirectoryInterface;
use App\Context\Scheduling\Application\Port\NotificationRequesterInterface;
use App\Context\Scheduling\Domain\Appointment;
use App\Context\Scheduling\Domain\Notification\AppointmentNotificationStateRepositoryInterface;
use App\Context\Scheduling\Domain\Notification\ReminderSettingsRepositoryInterface;
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Under the appointment's state lock, re-checks everything at send time:
 * the reminder is still due and not requested, the appointment is still
 * planned and to come, the owner's « Rappels de rendez-vous » consent allows
 * SMS and a mobile exists. The due time is first recomputed from the
 * appointment's current start and the clinic's delay — a move not synced
 * yet (debounce window) may have pushed it back: it is then only replanned;
 * a new owner is reminded only once the sync has confirmed the appointment
 * to them.
 * Then requests the SMS (non-critical: the e-mail fallback is passed only
 * when the same consent allows e-mail) and marks it requested — one reminder
 * per appointment, ever. A reminder that cannot go any more is dropped (not
 * scanned again).
 */
#[AsMessageHandler]
final readonly class RequestAppointmentReminderHandler
{
    public function __construct(
        private AppointmentNotificationStateRepositoryInterface $states,
        private AppointmentRepositoryInterface $appointments,
        private ReminderSettingsRepositoryInterface $settings,
        private ClinicDirectoryInterface $clinics,
        private ClientContactInterface $contacts,
        private AppointmentMessageComposer $composer,
        private NotificationRequesterInterface $notifications,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RequestAppointmentReminder $command): void
    {
        $appointmentId = AppointmentId::fromString($command->appointmentId);
        $now           = $this->clock->now();

        $state = $this->states->findForUpdate($appointmentId);

        if (null === $state || !$state->isReminderDue($now)) {
            return;
        }

        $appointment = $this->appointments->findById($appointmentId);

        if (null === $appointment || !self::isToCome($appointment, $now)) {
            $state->dropReminder();
            $this->states->save($state);

            return;
        }

        $clinic = $this->clinics->clinic($appointment->clinicId()->toString());
        $state->replanReminder($this->settings->reminderDelay($appointment->clinicId()), $appointment->timeSlot()->startsAtUtc(), $clinic->timeZone, $now);

        // Moved later meanwhile (replanned), too close to its start now (dropped), or given to
        // another client whom the pending sync has not told yet (the next scan sends it).
        $toldOwner = $state->notifiedOwnerId();

        if (!$state->isReminderDue($now) || (null !== $toldOwner && $toldOwner !== $appointment->ownerId())) {
            $this->states->save($state);

            return;
        }

        $contact = $this->reachableOwnerOf($appointment);

        if (null === $contact) {
            $state->dropReminder();
            $this->states->save($state);

            return;
        }

        $this->notifications->request($this->composer->reminder($appointment, $contact, $clinic, $now));

        $state->markReminderRequested($now);
        $this->states->save($state);
    }

    private static function isToCome(Appointment $appointment, \DateTimeImmutable $now): bool
    {
        return AppointmentStatus::PLANNED === $appointment->status() && $appointment->timeSlot()->startsAtUtc() > $now;
    }

    /** The owner to remind by SMS, if they accept it. */
    private function reachableOwnerOf(Appointment $appointment): ?ClientContact
    {
        $ownerId = $appointment->ownerId();

        if (null === $ownerId) {
            return null;
        }

        $contact = $this->contacts->contactOf($appointment->clinicId()->toString(), $ownerId);

        return null !== $contact && !$contact->archived && null !== $contact->mobile && $contact->acceptsReminderOn(ClientContact::SMS)
            ? $contact
            : null;
    }
}
