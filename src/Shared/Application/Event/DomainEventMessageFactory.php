<?php

declare(strict_types=1);

namespace App\Shared\Application\Event;

use App\Shared\Domain\Event\DomainEventInterface;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;

final readonly class DomainEventMessageFactory
{
    public function __construct(
        private UuidGeneratorInterface $uuidGenerator,
    ) {
    }

    public function wrap(DomainEventInterface $event, \DateTimeImmutable $occurredAt): DomainEventMessage
    {
        return new DomainEventMessage(
            eventId: $this->uuidGenerator->generate(),
            occurredAt: $occurredAt,
            event: $event,
        );
    }
}
