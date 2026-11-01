<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\EventSubscriber;

use App\Context\Scheduling\Application\Port\NotificationJobsInterface;
use App\Context\Scheduling\Domain\Event\AppointmentCancelled;
use App\Context\Scheduling\Domain\Event\AppointmentConfirmed;
use App\Context\Scheduling\Domain\Event\AppointmentDeclined;
use App\Context\Scheduling\Domain\Event\AppointmentRequested;
use App\Context\Scheduling\Domain\Event\AppointmentScheduled;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The bell of the appointment's veterinarian (D13): a booking made by
 * someone else queues the « Nouveau RDV » entry, a cancellation queues its
 * withdrawal. An online request queues « RDV en ligne à confirmer »
 * instead, withdrawn once confirmed, declined or withdrawn; a confirmation
 * posts no « Nouveau RDV » (the practitioner already knew). All only queue a
 * job on `async`, in the transaction of the change; the worker reads what
 * the entry needs.
 */
final readonly class NotifyPractitionerOnAppointmentScheduled
{
    public function __construct(private NotificationJobsInterface $jobs)
    {
    }

    #[AsMessageHandler(bus: 'messenger.bus.event')]
    public function onScheduled(AppointmentScheduled $event): void
    {
        if ($event->actorUserId() === $event->practitionerUserId()) {
            return;
        }

        $this->jobs->postNewAppointmentNotice($event->appointmentId(), $event->actorUserId());
    }

    #[AsMessageHandler(bus: 'messenger.bus.event')]
    public function onCancelled(AppointmentCancelled $event): void
    {
        $this->jobs->withdrawNewAppointmentNotice($event->appointmentId());

        if ($event->wasPending()) {
            $this->jobs->withdrawPendingBookingNotice($event->appointmentId());
        }
    }

    #[AsMessageHandler(bus: 'messenger.bus.event')]
    public function onRequested(AppointmentRequested $event): void
    {
        $this->jobs->postPendingBookingNotice($event->appointmentId());
    }

    #[AsMessageHandler(bus: 'messenger.bus.event')]
    public function onConfirmed(AppointmentConfirmed $event): void
    {
        $this->jobs->withdrawPendingBookingNotice($event->appointmentId());
    }

    #[AsMessageHandler(bus: 'messenger.bus.event')]
    public function onDeclined(AppointmentDeclined $event): void
    {
        $this->jobs->withdrawPendingBookingNotice($event->appointmentId());
    }
}
