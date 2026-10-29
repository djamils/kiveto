<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Messaging\Consumer;

use App\Context\Client\Domain\Event\ClientArchivedIntegrationEvent;
use App\Context\ClientPortal\Application\Command\RevokeClientPortalAccess\RevokeClientPortalAccess;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/** An archived client loses its Portal access (pending invitations and links). */
#[AsMessageHandler(bus: 'messenger.bus.integration_event')]
final readonly class ClientArchivedIntegrationEventConsumer
{
    public function __construct(private CommandBusInterface $commandBus)
    {
    }

    public function __invoke(ClientArchivedIntegrationEvent $event): void
    {
        $payload  = $event->payload();
        $clinicId = $payload['clinicId'];
        $clientId = $payload['clientId'];
        \assert(\is_string($clinicId) && \is_string($clientId));

        $this->commandBus->dispatch(new RevokeClientPortalAccess($clinicId, $clientId));
    }
}
