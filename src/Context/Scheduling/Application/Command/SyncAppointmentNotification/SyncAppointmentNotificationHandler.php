<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\SyncAppointmentNotification;

use App\Context\Scheduling\Application\Notification\AppointmentMessageComposer;
use App\Context\Scheduling\Application\Port\ClientContact;
use App\Context\Scheduling\Application\Port\ClientContactInterface;
use App\Context\Scheduling\Application\Port\ClinicCard;
use App\Context\Scheduling\Application\Port\ClinicDirectoryInterface;
use App\Context\Scheduling\Application\Port\NotificationRequesterInterface;
use App\Context\Scheduling\Domain\Appointment;
use App\Context\Scheduling\Domain\Notification\AppointmentMessageKind;
use App\Context\Scheduling\Domain\Notification\AppointmentNotificationState;
use App\Context\Scheduling\Domain\Notification\AppointmentNotificationStateRepositoryInterface;
use App\Context\Scheduling\Domain\Notification\ReminderSettingsRepositoryInterface;
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Shared\Domain\Time\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Locks the appointment's notification state first (created when absent;
 * held until COMMIT — a lock-wait timeout fails the message and Messenger
 * retries it), so everything read afterwards is at least as recent as the
 * previous sync's commit; then derives the single message the appointment
 * calls for now (confirmation, change, cancellation or nothing), requests it
 * on the owner's transactional channel and records it, and replans the
 * reminder from the current slot. An owner who cannot be reached gets
 * nothing (logged); the message is then derived again at the next sync.
 *
 * Owner change: the client who was told about the appointment hears of its
 * cancellation, then the new owner gets a confirmation (if reachable) —
 * one message per client. A cancellation always goes to the client who was
 * told, not to an owner set meanwhile.
 */
#[AsMessageHandler]
final readonly class SyncAppointmentNotificationHandler
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
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(SyncAppointmentNotification $command): void
    {
        $appointmentId = AppointmentId::fromString($command->appointmentId);

        $state       = $this->states->lock($appointmentId);
        $appointment = null !== $state ? $this->appointments->findById($appointmentId) : null;

        if (null === $state || null === $appointment) {
            return;
        }

        $now    = $this->clock->now();
        $clinic = $this->clinics->clinic($appointment->clinicId()->toString());

        if (AppointmentStatus::PLANNED === $appointment->status()) {
            $delay = $this->settings->reminderDelay($appointment->clinicId());
            $state->planReminder($delay, $appointment->timeSlot()->startsAtUtc(), $clinic->timeZone, $now);
        } else {
            $state->dropReminder();
        }

        $formerOwnerId = $state->replacedOwner($appointment->status(), $appointment->ownerId(), $now);

        if (null !== $formerOwnerId) {
            $this->notify(AppointmentMessageKind::CANCELLATION, $formerOwnerId, $appointment, $state, $clinic, $now);
            $state->releaseOwner();
        }

        $kind = $state->nextMessage(
            $appointment->status(),
            $appointment->timeSlot(),
            $appointment->practitionerAssignee()->userId()->toString(),
            $now,
        );

        if (null !== $kind) {
            $recipient = AppointmentMessageKind::CANCELLATION === $kind ? $state->notifiedOwnerId() ?? $appointment->ownerId() : $appointment->ownerId();
            $this->notify($kind, $recipient, $appointment, $state, $clinic, $now);
        }

        $this->states->save($state);
    }

    private function notify(AppointmentMessageKind $kind, ?string $clientId, Appointment $appointment, AppointmentNotificationState $state, ClinicCard $clinic, \DateTimeImmutable $now): void
    {
        $contact = $this->reachable($appointment, $clientId);
        $channel = $contact?->transactionalChannel;

        if (null === $contact || null === $channel) {
            $this->logger->info('Appointment {appointment}: no {kind} message, the owner cannot be reached.', [
                'appointment' => $appointment->id()->toString(),
                'kind'        => $kind->value,
            ]);

            return;
        }

        $notified = $state->notified();
        $number   = $state->recordNotified(
            $kind,
            $appointment->timeSlot(),
            $appointment->practitionerAssignee()->userId()->toString(),
            $now,
            $clientId,
        );

        $this->notifications->request(
            $this->composer->transactional($kind, $number, $appointment, $notified, $contact, $channel, $clinic, $now),
        );
    }

    /** The client, unless there is none or they were archived. */
    private function reachable(Appointment $appointment, ?string $clientId): ?ClientContact
    {
        $contact = null !== $clientId ? $this->contacts->contactOf($appointment->clinicId()->toString(), $clientId) : null;

        return null !== $contact && !$contact->archived ? $contact : null;
    }
}
