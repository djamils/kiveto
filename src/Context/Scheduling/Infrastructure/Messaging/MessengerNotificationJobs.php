<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Messaging;

use App\Context\Scheduling\Application\Command\RequestAppointmentReminder\RequestAppointmentReminder;
use App\Context\Scheduling\Application\Command\SyncAppointmentNotification\SyncAppointmentNotification;
use App\Context\Scheduling\Application\Port\NotificationJobsInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

/**
 * Both commands are routed to `async` (messenger.yaml) and dispatched on the
 * raw command bus — the application command bus expects a handled result.
 * The Doctrine transport writes them in the current transaction (outbox):
 * no sync is queued for an appointment change that is rolled back.
 */
final readonly class MessengerNotificationJobs implements NotificationJobsInterface
{
    public function __construct(
        #[Autowire(service: 'messenger.bus.command')]
        private MessageBusInterface $commandBus,
        #[Autowire(param: 'scheduling.notification.sync_delay')]
        private int $syncDelay,
    ) {
    }

    public function syncLater(string $appointmentId): void
    {
        $this->commandBus->dispatch(new SyncAppointmentNotification($appointmentId), [new DelayStamp($this->syncDelay)]);
    }

    public function requestReminder(string $appointmentId): void
    {
        $this->commandBus->dispatch(new RequestAppointmentReminder($appointmentId));
    }
}
