<?php

declare(strict_types=1);

namespace App\Shared\Domain\Aggregate;

use App\Shared\Domain\Event\DomainEventInterface;

abstract class AggregateRoot
{
    /** @var list<DomainEventInterface> */
    private array $recordedDomainEvents = [];

    /**
     * Pulls and clears domain events.
     *
     * The application layer should call this after persistence/transaction,
     * then publish events through a DomainEventBus.
     *
     * @return list<DomainEventInterface>
     */
    #[\NoDiscard]
    final public function pullDomainEvents(): array
    {
        $events                     = $this->recordedDomainEvents;
        $this->recordedDomainEvents = [];

        return $events;
    }

    /**
     * Returns recorded domain events without clearing them (useful for tests/debug).
     *
     * @return list<DomainEventInterface>
     */
    final public function recordedDomainEvents(): array
    {
        return $this->recordedDomainEvents;
    }

    final protected function recordDomainEvent(DomainEventInterface $event): void
    {
        $this->recordedDomainEvents[] = $event;
    }

    final protected function clearDomainEvents(): void
    {
        $this->recordedDomainEvents = [];
    }
}
