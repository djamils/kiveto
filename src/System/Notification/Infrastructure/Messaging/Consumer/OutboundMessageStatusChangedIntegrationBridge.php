<?php

declare(strict_types=1);

namespace App\System\Notification\Infrastructure\Messaging\Consumer;

use App\Shared\Application\Event\IntegrationEventPublisher;
use App\System\Notification\Domain\Event\OutboundMessageStatusChanged;
use App\System\Notification\Domain\Event\OutboundMessageStatusChangedIntegrationEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Publishes every status a message takes to the other contexts, in the
 * transaction of the change (outbox).
 */
#[AsMessageHandler(bus: 'messenger.bus.event')]
final readonly class OutboundMessageStatusChangedIntegrationBridge
{
    public function __construct(private IntegrationEventPublisher $publisher)
    {
    }

    public function __invoke(OutboundMessageStatusChanged $event): void
    {
        $this->publisher->publish(new OutboundMessageStatusChangedIntegrationEvent(
            id: $event->messageId,
            clinicId: $event->clinicId,
            clientId: $event->clientId,
            purpose: $event->purpose,
            channel: $event->channel,
            recipientLabel: $event->recipientLabel,
            subjectLine: $event->subjectLine,
            status: $event->status,
            statusLabel: $event->statusLabel,
            reason: $event->reason,
            occurredAt: $event->occurredAt,
            sequence: $event->sequence,
        ));
    }
}
