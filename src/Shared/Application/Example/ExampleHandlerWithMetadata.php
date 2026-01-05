<?php

declare(strict_types=1);

namespace App\Shared\Application\Example;

use App\Shared\Application\Messaging\MessageContext;

/**
 * Example handler showing how to access message metadata via MessageContext.
 *
 * In any CQRS handler (command/query/event), you can inject MessageContext
 * to access metadata of the currently processed message:
 * - messageId: unique ID of the message
 * - occurredAt: when the message was created
 * - correlationId: tracks related messages in a flow
 * - causationId: the message that triggered this one
 * - actorId: the user who initiated the flow (if available)
 */
final readonly class ExampleHandlerWithMetadata
{
    public function __construct(private MessageContext $messageContext)
    {
    }

    public function __invoke(object $command): void
    {
        // Access current message metadata
        $messageId     = $this->messageContext->messageId();
        $correlationId = $this->messageContext->correlationId();
        $causationId   = $this->messageContext->causationId();
        $actorId       = $this->messageContext->actorId();

        // Use metadata for logging, auditing, or business logic

        // Business logic here...
    }
}
