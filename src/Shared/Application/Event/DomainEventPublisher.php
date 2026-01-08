<?php

declare(strict_types=1);

namespace App\Shared\Application\Event;

use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Domain\Aggregate\AggregateRoot;

/**
 * Publishes domain events from aggregates without wrapping.
 * Events are published as-is (typed events), metadata is handled by Messenger middleware.
 * MessageMetadataMiddleware will auto-complete metadata (messageId, occurredAt, correlationId, causationId, actorId).
 */
final readonly class DomainEventPublisher
{
    public function __construct(private EventBusInterface $eventBus)
    {
    }

    public function publish(AggregateRoot $aggregate): void
    {
        $events = $aggregate->pullDomainEvents();

        if ([] === $events) {
            return;
        }

        // Publish events without wrapping
        // MessageMetadataMiddleware will add metadata stamps automatically
        // occurredAt is captured here but middleware will use Clock->now()
        // This is acceptable as they should be very close in time
        $this->eventBus->publish([], ...$events);
    }
}
