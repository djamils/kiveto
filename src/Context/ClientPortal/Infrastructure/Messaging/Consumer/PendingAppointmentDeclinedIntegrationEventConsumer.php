<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Messaging\Consumer;

use App\Context\ClientPortal\Application\Command\ClosePortalRequestsForSubject\ClosePortalRequestsForSubject;
use App\Context\ClientPortal\Domain\Request\ClosureReason;
use App\Context\ClientPortal\Domain\Request\RequestSubject;
use App\Context\Scheduling\Domain\Event\PendingAppointmentDeclinedIntegrationEvent;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/** A pending online booking declined (or expired) by the clinic: its questionnaire is closed. */
#[AsMessageHandler(bus: 'messenger.bus.integration_event')]
final readonly class PendingAppointmentDeclinedIntegrationEventConsumer
{
    public function __construct(private CommandBusInterface $commandBus)
    {
    }

    public function __invoke(PendingAppointmentDeclinedIntegrationEvent $event): void
    {
        $this->commandBus->dispatch(new ClosePortalRequestsForSubject(RequestSubject::APPOINTMENT, $event->appointmentId, ClosureReason::APPOINTMENT_DECLINED, $event->clinicId));
    }
}
