<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Messaging\Consumer;

use App\Context\ClientPortal\Application\Command\ClosePortalRequestsForSubject\ClosePortalRequestsForSubject;
use App\Context\ClientPortal\Application\Command\WithdrawPublishedDocument\WithdrawPublishedDocument;
use App\Context\ClientPortal\Domain\Document\DocumentType;
use App\Context\ClientPortal\Domain\Request\ClosureReason;
use App\Context\ClientPortal\Domain\Request\RequestSubject;
use App\Context\Prescription\Domain\Event\PrescriptionCancelledIntegrationEvent;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/** A cancelled prescription leaves the Portal; its open renewal request is closed (`prescription_cancelled`). */
#[AsMessageHandler(bus: 'messenger.bus.integration_event')]
final readonly class PrescriptionCancelledIntegrationEventConsumer
{
    public function __construct(private CommandBusInterface $commandBus)
    {
    }

    public function __invoke(PrescriptionCancelledIntegrationEvent $event): void
    {
        $this->commandBus->dispatch(new WithdrawPublishedDocument($event->clinicId, DocumentType::PRESCRIPTION, $event->prescriptionId));
        $this->commandBus->dispatch(new ClosePortalRequestsForSubject(RequestSubject::PRESCRIPTION, $event->prescriptionId, ClosureReason::PRESCRIPTION_CANCELLED, $event->clinicId));
    }
}
