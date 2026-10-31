<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Messaging\Consumer;

use App\Context\Billing\Domain\Event\InvoiceIssuedForPortalIntegrationEvent;
use App\Context\ClientPortal\Application\Command\PublishInvoice\PublishInvoice;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/** Every issued invoice is offered to the Portal; PublishInvoice keeps those of a payer client. */
#[AsMessageHandler(bus: 'messenger.bus.integration_event')]
final readonly class InvoiceIssuedForPortalIntegrationEventConsumer
{
    public function __construct(private CommandBusInterface $commandBus)
    {
    }

    public function __invoke(InvoiceIssuedForPortalIntegrationEvent $event): void
    {
        $this->commandBus->dispatch(new PublishInvoice($event->clinicId, $event->invoiceId));
    }
}
