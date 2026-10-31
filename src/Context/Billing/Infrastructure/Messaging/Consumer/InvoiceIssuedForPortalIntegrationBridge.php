<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Messaging\Consumer;

use App\Context\Billing\Domain\Event\InvoiceIssued;
use App\Context\Billing\Domain\Event\InvoiceIssuedForPortalIntegrationEvent;
use App\Shared\Application\Event\IntegrationEventPublisher;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Any invoice is issued: announce it to the Client Portal, written to the
 * outbox in the issuing transaction. Whether it is published (a payer client,
 * a confirmed link) is the Portal's call. The B2B transmission has its own
 * bridge (InvoiceIssuedIntegrationBridge).
 */
#[AsMessageHandler(bus: 'messenger.bus.event')]
final readonly class InvoiceIssuedForPortalIntegrationBridge
{
    public function __construct(private IntegrationEventPublisher $integrationEventPublisher)
    {
    }

    public function __invoke(InvoiceIssued $event): void
    {
        $this->integrationEventPublisher->publish(
            new InvoiceIssuedForPortalIntegrationEvent(invoiceId: $event->invoiceId, clinicId: $event->clinicId),
        );
    }
}
