<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Messaging\Consumer;

use App\Context\Billing\Domain\Event\InvoiceCancelledIntegrationEvent;
use App\Context\ClientPortal\Application\Command\WithdrawPublishedDocument\WithdrawPublishedDocument;
use App\Context\ClientPortal\Domain\Document\DocumentType;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/** A cancelled invoice leaves the Portal (a credited one stays). */
#[AsMessageHandler(bus: 'messenger.bus.integration_event')]
final readonly class InvoiceCancelledIntegrationEventConsumer
{
    public function __construct(private CommandBusInterface $commandBus)
    {
    }

    public function __invoke(InvoiceCancelledIntegrationEvent $event): void
    {
        $this->commandBus->dispatch(new WithdrawPublishedDocument($event->clinicId, DocumentType::INVOICE, $event->invoiceId));
    }
}
