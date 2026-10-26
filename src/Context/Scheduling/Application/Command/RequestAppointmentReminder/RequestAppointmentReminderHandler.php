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
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Under the appointment's state lock, re-checks everything at send time:
 * the reminder is still due and not requested, the appointment is still
 * planned and to come, the owner's « Rappels de rendez-vous » consent allows
 * SMS and a mobile exists. Then requests the SMS (non-critical: the e-mail
 * fallback is passed only when the same consent allows e-mail) and marks it
 * requested — one reminder per appointment, ever. A reminder that cannot go
 * any more is dropped (not scanned again).
 */
#[AsMessageHandler]
final readonly class RequestAppointmentReminderHandler
{
    public function __construct(
        private AppointmentNotificationStateRepositoryInterface $states,
        private AppointmentRepositoryInterface $appointments,
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
        $contact     = null !== $appointment ? $this->reachableOwnerOf($appointment, $now) : null;

        if (null === $appointment || null === $contact) {
            $state->dropReminder();
            $this->states->save($state);

            return;
        }

        $clinic = $this->clinics->clinic($appointment->clinicId()->toString());
        $this->notifications->request($this->composer->reminder($appointment, $contact, $clinic, $now));

        $state->markReminderRequested($now);
        $this->states->save($state);
    }

    /** The owner to remind by SMS, if the appointment still calls for it. */
    private function reachableOwnerOf(Appointment $appointment, \DateTimeImmutable $now): ?ClientContact
    {
        $ownerId = $appointment->ownerId();

        if (AppointmentStatus::PLANNED !== $appointment->status() || $appointment->timeSlot()->startsAtUtc() <= $now || null === $ownerId) {
            return null;
        }

        $contact = $this->contacts->contactOf($appointment->clinicId()->toString(), $ownerId);

        return null !== $contact && !$contact->archived && null !== $contact->mobile && $contact->acceptsReminderOn(ClientContact::SMS)
            ? $contact
            : null;
    }
}
