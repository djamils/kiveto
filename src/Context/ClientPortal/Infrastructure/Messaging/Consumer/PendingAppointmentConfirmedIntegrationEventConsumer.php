<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Messaging\Consumer;

use App\Context\ClientPortal\Application\Command\SyncBookingRequestFromAppointment\SyncBookingRequestFromAppointment;
use App\Context\Scheduling\Domain\Event\PendingAppointmentConfirmedIntegrationEvent;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/** A pending online booking confirmed: a new client's booking request follows (`confirmed`). */
#[AsMessageHandler(bus: 'messenger.bus.integration_event')]
final readonly class PendingAppointmentConfirmedIntegrationEventConsumer
{
    public function __construct(private CommandBusInterface $commandBus)
    {
    }

    public function __invoke(PendingAppointmentConfirmedIntegrationEvent $event): void
    {
        $this->commandBus->dispatch(new SyncBookingRequestFromAppointment($event->appointmentId, SyncBookingRequestFromAppointment::CONFIRMED, $event->ownerId));
    }
}
