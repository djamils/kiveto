<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Messaging\Consumer;

use App\Context\ClientPortal\Domain\Event\ClientLinkConfirmed;
use App\Context\ClientPortal\Domain\Event\ClientLinkConfirmedIntegrationEvent;
use App\Shared\Application\Event\IntegrationEventPublisher;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'messenger.bus.event')]
final readonly class ClientLinkConfirmedIntegrationBridge
{
    public function __construct(private IntegrationEventPublisher $integrationEventPublisher)
    {
    }

    public function __invoke(ClientLinkConfirmed $event): void
    {
        $this->integrationEventPublisher->publish(new ClientLinkConfirmedIntegrationEvent(
            linkId: $event->linkId,
            userId: $event->userId,
            clinicId: $event->clinicId,
            clientId: $event->clientId,
        ));
    }
}
