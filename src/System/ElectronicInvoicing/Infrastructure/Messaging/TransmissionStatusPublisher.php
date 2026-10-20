<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Infrastructure\Messaging;

use App\Shared\Application\Event\IntegrationEventPublisher;
use App\System\ElectronicInvoicing\Application\Query\GetTransmissionStatus\TransmissionStatusView;
use App\System\ElectronicInvoicing\Domain\Event\InvoiceTransmissionStatusChangedIntegrationEvent;
use App\System\ElectronicInvoicing\Domain\Transmission\OutboundTransmissionId;
use App\System\ElectronicInvoicing\Domain\Transmission\OutboundTransmissionRepositoryInterface;

/**
 * Publishes the current view of a transmission after one of its changes, in
 * the transaction of the change (outbox).
 */
final readonly class TransmissionStatusPublisher
{
    public function __construct(
        private OutboundTransmissionRepositoryInterface $transmissions,
        private IntegrationEventPublisher $integrationEventPublisher,
    ) {
    }

    public function publish(string $transmissionId, string $occurredAt): void
    {
        $view = TransmissionStatusView::of($this->transmissions->get(OutboundTransmissionId::fromString($transmissionId)));

        $this->integrationEventPublisher->publish(new InvoiceTransmissionStatusChangedIntegrationEvent(
            clinicId: $view->clinicId,
            documentId: $view->documentId,
            sequence: $view->sequence,
            state: $view->state,
            blockReason: $view->blockReason,
            statusCode: $view->statusCode,
            statusLabel: $view->statusLabel,
            reasonCode: $view->reasonCode,
            reasonText: $view->reasonText,
            requiresAction: $view->requiresAction,
            occurredAt: $occurredAt,
        ));
    }
}
