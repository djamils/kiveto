<?php

declare(strict_types=1);

namespace App\Shared\Application\Messaging;

use App\Shared\Infrastructure\Bus\Messenger\Stamp\MessageMetadataStamp;

/**
 * Context storing a stack of message metadata for nested dispatches.
 * Allows handlers to access metadata of the currently processed message.
 */
final class MessageContext
{
    /** @var list<MessageMetadataStamp> */
    private array $stack = [];

    public function push(MessageMetadataStamp $metadata): void
    {
        $this->stack[] = $metadata;
    }

    public function pop(): void
    {
        if ([] === $this->stack) {
            throw new \LogicException('Cannot pop from empty message context stack.');
        }

        array_pop($this->stack);
    }

    public function current(): MessageMetadataStamp
    {
        if ([] === $this->stack) {
            throw new \LogicException('No message metadata in context. Ensure MessageContextMiddleware is configured.');
        }

        return $this->stack[\count($this->stack) - 1];
    }

    public function messageId(): string
    {
        return $this->current()->messageId;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->current()->occurredAt;
    }

    public function correlationId(): ?string
    {
        return $this->current()->correlationId;
    }

    public function causationId(): ?string
    {
        return $this->current()->causationId;
    }

    public function actorId(): ?string
    {
        return $this->current()->actorId;
    }
}
