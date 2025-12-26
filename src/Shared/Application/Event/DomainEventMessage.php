<?php

declare(strict_types=1);

namespace App\Shared\Application\Event;

use App\Shared\Domain\Event\DomainEventInterface;

/**
 * Envelope wrapping a pure domain event with transport metadata.
 */
readonly class DomainEventMessage
{
    public function __construct(
        private string $eventId,
        private \DateTimeImmutable $occurredAt,
        private DomainEventInterface $event,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function event(): DomainEventInterface
    {
        return $this->event;
    }
}
