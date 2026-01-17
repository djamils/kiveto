<?php

declare(strict_types=1);

namespace App\Shared\Application\Event;

use App\Shared\Application\Bus\IntegrationEventBusInterface;

/**
 * Publishes integration events (cross-bounded contexts) without wrapping.
 * Events are published as-is (typed events), metadata is handled by Messenger middleware.
 * MessageMetadataMiddleware will auto-complete metadata (messageId, occurredAt, correlationId, causationId, actorId).
 */
final readonly class IntegrationEventPublisher
{
    public function __construct(private IntegrationEventBusInterface $integrationEventBus)
    {
    }

    public function publish(object ...$events): void
    {
        if ([] === $events) {
            return;
        }

        // Publish events without wrapping
        // MessageMetadataMiddleware will add metadata stamps automatically
        $this->integrationEventBus->publish([], ...$events);
    }
}
