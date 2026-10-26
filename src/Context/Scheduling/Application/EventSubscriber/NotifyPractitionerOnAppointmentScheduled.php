<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\EventSubscriber;

use App\Context\Scheduling\Application\Port\NotificationJobsInterface;
use App\Context\Scheduling\Domain\Event\AppointmentCancelled;
use App\Context\Scheduling\Domain\Event\AppointmentScheduled;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The bell of the appointment's veterinarian (D13): a booking made by
 * someone else queues the « Nouveau RDV » entry, a cancellation queues its
 * withdrawal. Both only queue a job on `async`, in the transaction of the
 * change; the worker reads what the entry needs.
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
    }
}
