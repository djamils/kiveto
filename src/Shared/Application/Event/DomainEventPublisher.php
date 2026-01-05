<?php

declare(strict_types=1);

namespace App\Shared\Application\Event;

use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Domain\Aggregate\AggregateRoot;

final readonly class DomainEventPublisher
{
    public function __construct(
        private EventBusInterface $eventBus,
        private DomainEventMessageFactory $messageFactory,
    ) {
    }

    public function publish(AggregateRoot $aggregate, \DateTimeImmutable $occurredAt): void
    {
        $messages = [];

        foreach ($aggregate->pullDomainEvents() as $event) {
            $messages[] = $this->messageFactory->wrap($event, $occurredAt);
        }

        if ([] !== $messages) {
            $this->eventBus->publish(...$messages);
        }
    }
}
