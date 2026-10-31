<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Messaging\Consumer;

use App\Context\Billing\Domain\Event\InvoiceCancelled;
use App\Context\Billing\Domain\Event\InvoiceCancelledIntegrationEvent;
use App\Shared\Application\Event\IntegrationEventPublisher;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * An invoice is cancelled: announce it (outbox, cancelling transaction) so
 * the Client Portal withdraws it.
 */
#[AsMessageHandler(bus: 'messenger.bus.event')]
final readonly class InvoiceCancelledIntegrationBridge
{
    public function __construct(private IntegrationEventPublisher $integrationEventPublisher)
    {
    }

    public function __invoke(InvoiceCancelled $event): void
    {
        $this->integrationEventPublisher->publish(
            new InvoiceCancelledIntegrationEvent(invoiceId: $event->invoiceId, clinicId: $event->clinicId),
        );
    }
}
