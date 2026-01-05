<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus\Messenger\Middleware;

use App\Shared\Application\Messaging\MessageContext;
use App\Shared\Application\Security\ActorContext;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Infrastructure\Bus\Messenger\Stamp\MessageMetadataStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

/**
 * Middleware ensuring every dispatched message has a MessageMetadataStamp.
 * Auto-completes missing metadata using UuidGenerator, Clock, MessageContext and ActorContext.
 */
final readonly class MessageMetadataMiddleware implements MiddlewareInterface
{
    public function __construct(
        private UuidGeneratorInterface $uuidGenerator,
        private ClockInterface $clock,
        private MessageContext $messageContext,
        private ActorContext $actorContext,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        // If metadata stamp already exists, don't override it
        if (null !== $envelope->last(MessageMetadataStamp::class)) {
            return $stack->next()->handle($envelope, $stack);
        }

        // Generate metadata
        $messageId  = $this->uuidGenerator->generate();
        $occurredAt = $this->clock->now();

        // Derive correlationId from current context or generate new one
        $correlationId = null;
        $causationId   = null;

        try {
            $currentMetadata = $this->messageContext->current();
            $correlationId   = $currentMetadata->correlationId;
            $causationId     = $currentMetadata->messageId;
        } catch (\LogicException) {
            // No current context = this is a root message, generate new correlationId
            $correlationId = $this->uuidGenerator->generate();
        }

        $actorId = $this->actorContext->get();

        $stamp = new MessageMetadataStamp(
            messageId: $messageId,
            occurredAt: $occurredAt,
            correlationId: $correlationId,
            causationId: $causationId,
            actorId: $actorId,
        );

        return $stack->next()->handle($envelope->with($stamp), $stack);
    }
}
