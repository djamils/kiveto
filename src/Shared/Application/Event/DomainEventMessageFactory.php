<?php

declare(strict_types=1);

namespace App\Shared\Application\Event;

use App\Shared\Domain\Event\DomainEventInterface;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;

final readonly class DomainEventMessageFactory
{
    public function __construct(
        private UuidGeneratorInterface $uuidGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function wrap(DomainEventInterface $event): DomainEventMessage
    {
        return new DomainEventMessage(
            eventId: $this->uuidGenerator->generate(),
            occurredAt: $this->clock->now(),
            event: $event,
        );
    }
}
