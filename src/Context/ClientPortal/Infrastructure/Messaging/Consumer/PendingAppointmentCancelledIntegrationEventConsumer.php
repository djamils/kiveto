<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Messaging\Consumer;

use App\Context\ClientPortal\Application\Command\ClosePortalRequestsForSubject\ClosePortalRequestsForSubject;
use App\Context\ClientPortal\Application\Command\SyncBookingRequestFromAppointment\SyncBookingRequestFromAppointment;
use App\Context\ClientPortal\Domain\Request\ClosureReason;
use App\Context\ClientPortal\Domain\Request\RequestSubject;
use App\Context\Scheduling\Domain\Event\PendingAppointmentCancelledIntegrationEvent;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * A pending online booking withdrawn by its owner: its questionnaire is
 * closed. Other cancellations are caught at read time (the appointment's
 * status hides the questionnaire). A new client's booking request still
 * pending follows (`cancelled`).
 */
#[AsMessageHandler(bus: 'messenger.bus.integration_event')]
final readonly class PendingAppointmentCancelledIntegrationEventConsumer
{
    public function __construct(private CommandBusInterface $commandBus)
    {
    }

    public function __invoke(PendingAppointmentCancelledIntegrationEvent $event): void
    {
        $this->commandBus->dispatch(new ClosePortalRequestsForSubject(RequestSubject::APPOINTMENT, $event->appointmentId, ClosureReason::APPOINTMENT_CANCELLED, $event->clinicId));
        $this->commandBus->dispatch(new SyncBookingRequestFromAppointment($event->appointmentId, SyncBookingRequestFromAppointment::CANCELLED));
    }
}
