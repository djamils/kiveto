<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus\Messenger\Middleware;

use App\Shared\Application\Messaging\MessageContext;
use App\Shared\Infrastructure\Bus\Messenger\Stamp\MessageMetadataStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

/**
 * Middleware managing the message context stack.
 * Pushes metadata when handling a message, pops it after completion.
 * Enables nested dispatches and context access in handlers.
 */
final readonly class MessageContextMiddleware implements MiddlewareInterface
{
    public function __construct(private MessageContext $messageContext)
    {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        /** @var MessageMetadataStamp|null $stamp */
        $stamp = $envelope->last(MessageMetadataStamp::class);

        // If no metadata stamp, just continue (MessageMetadataMiddleware should have added it)
        if (null === $stamp) {
            return $stack->next()->handle($envelope, $stack);
        }

        // Push metadata to context
        $this->messageContext->push($stamp);

        try {
            return $stack->next()->handle($envelope, $stack);
        } finally {
            // Always pop, even if an exception occurred
            $this->messageContext->pop();
        }
    }
}
