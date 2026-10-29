<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\EventSubscriber;

use App\Context\Scheduling\Application\Port\NotificationJobsInterface;
use App\Context\Scheduling\Domain\Event\AppointmentCancelled;
use App\Context\Scheduling\Domain\Event\AppointmentOwnerChanged;
use App\Context\Scheduling\Domain\Event\AppointmentPractitionerAssigneeChanged;
use App\Context\Scheduling\Domain\Event\AppointmentRescheduled;
use App\Context\Scheduling\Domain\Event\AppointmentScheduled;
use App\Context\Scheduling\Domain\Notification\AppointmentNotificationStateRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Every appointment event queues the same debounced sync of the client
 * messages, 2 minutes later (D2) — in the transaction of the change, so a
 * rolled-back change queues nothing. Several events of one change, or of
 * changes in a row, give several syncs: the first one tells the final state,
 * the others find nothing left to tell. A cancellation the client must not
 * be told about (death of the animal, D3) first marks the appointment's
 * notification state silenced — the only write done here.
 */
final readonly class ScheduleAppointmentNotificationSync
{
    public function __construct(
        private NotificationJobsInterface $jobs,
        private AppointmentNotificationStateRepositoryInterface $states,
    ) {
    }

    #[AsMessageHandler(bus: 'messenger.bus.event')]
    public function onScheduled(AppointmentScheduled $event): void
    {
        $this->jobs->syncLater($event->appointmentId());
    }

    #[AsMessageHandler(bus: 'messenger.bus.event')]
    public function onRescheduled(AppointmentRescheduled $event): void
    {
        $this->jobs->syncLater($event->appointmentId());
    }

    #[AsMessageHandler(bus: 'messenger.bus.event')]
    public function onPractitionerChanged(AppointmentPractitionerAssigneeChanged $event): void
    {
        $this->jobs->syncLater($event->appointmentId());
    }

    #[AsMessageHandler(bus: 'messenger.bus.event')]
    public function onOwnerChanged(AppointmentOwnerChanged $event): void
    {
        $this->jobs->syncLater($event->appointmentId());
    }

    #[AsMessageHandler(bus: 'messenger.bus.event')]
    public function onCancelled(AppointmentCancelled $event): void
    {
        if (!$event->notifyClient()) {
            $this->states->silence(AppointmentId::fromString($event->appointmentId()), ClinicId::fromString($event->clinicId()));
        }

        $this->jobs->syncLater($event->appointmentId());
    }
}
