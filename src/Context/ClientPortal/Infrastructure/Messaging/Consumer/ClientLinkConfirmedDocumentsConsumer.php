<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Messaging\Consumer;

use App\Context\ClientPortal\Application\Command\ReleaseAwaitingDocuments\ReleaseAwaitingDocuments;
use App\Context\ClientPortal\Domain\Event\ClientLinkConfirmedIntegrationEvent;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/** A confirmed link releases the client's documents that waited for it. */
#[AsMessageHandler(bus: 'messenger.bus.integration_event')]
final readonly class ClientLinkConfirmedDocumentsConsumer
{
    public function __construct(private CommandBusInterface $commandBus)
    {
    }

    public function __invoke(ClientLinkConfirmedIntegrationEvent $event): void
    {
        $this->commandBus->dispatch(new ReleaseAwaitingDocuments($event->clinicId, $event->clientId));
    }
}
