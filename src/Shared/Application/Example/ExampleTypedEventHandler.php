<?php

declare(strict_types=1);

namespace App\Shared\Application\Example;

use App\Shared\Application\Messaging\MessageContext;
use App\Shared\Domain\Event\DomainEventInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Example event handler showing how to handle TYPED domain events.
 *
 * Key points:
 * - Handler receives the event directly (not wrapped in DomainEventMessage)
 * - The __invoke method is typed with the specific event class
 * - MessageContext can be injected to access metadata
 * - All metadata is automatically propagated via MessageMetadataMiddleware
 */
#[AsMessageHandler(bus: 'messenger.bus.event')]
final readonly class ExampleTypedEventHandler
{
    public function __construct(private MessageContext $messageContext)
    {
    }

    /**
     * Handle a specific domain event (typed parameter).
     *
     * @param DomainEventInterface $event Replace with your concrete event class
     */
    public function __invoke(DomainEventInterface $event): void
    {
        // Access event payload
        $aggregateId = $event->aggregateId();
        $payload     = $event->payload();

        // Access message metadata via context
        $messageId     = $this->messageContext->messageId();
        $correlationId = $this->messageContext->correlationId();
        $causationId   = $this->messageContext->causationId();
        $actorId       = $this->messageContext->actorId();

        // React to the event
        // Example: update a read model, send a notification, etc.
    }
}
