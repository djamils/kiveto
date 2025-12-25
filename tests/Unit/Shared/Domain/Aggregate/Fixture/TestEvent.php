<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Aggregate\Fixture;

use App\Shared\Domain\Event\DomainEventInterface;

final class TestEvent implements DomainEventInterface
{
    public function __construct(
        private readonly string $eventId,
        private readonly string $aggregateId,
        private readonly \DateTimeImmutable $occurredAt = new \DateTimeImmutable('2025-01-01 00:00:00'),
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId;
    }

    public function aggregateId(): string
    {
        return $this->aggregateId;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function type(): string
    {
        return 'test.aggregate.event.v1';
    }

    public function payload(): array
    {
        return [];
    }
}
