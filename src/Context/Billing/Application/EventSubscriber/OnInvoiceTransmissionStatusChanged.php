<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\EventSubscriber;

use App\Context\Billing\Application\Command\RecordTransmissionStatus\RecordTransmissionStatus;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\ElectronicInvoicing\Domain\Event\InvoiceTransmissionStatusChangedIntegrationEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Projects what the electronic-invoicing context reports onto the invoice or
 * credit note it is about.
 */
#[AsMessageHandler(bus: 'messenger.bus.integration_event')]
final readonly class OnInvoiceTransmissionStatusChanged
{
    public function __construct(private CommandBusInterface $commandBus)
    {
    }

    public function __invoke(InvoiceTransmissionStatusChangedIntegrationEvent $event): void
    {
        $this->commandBus->dispatch(new RecordTransmissionStatus(
            documentId: $event->documentId,
            sequence: $event->sequence,
            state: $event->state,
            blockReason: $event->blockReason,
            statusCode: $event->statusCode,
            statusLabel: $event->statusLabel,
            reasonCode: $event->reasonCode,
            reasonText: $event->reasonText,
            requiresAction: $event->requiresAction,
            occurredAt: $event->occurredAt,
        ));
    }
}
