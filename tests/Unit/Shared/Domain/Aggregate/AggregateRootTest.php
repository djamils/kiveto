<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Aggregate;

use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Event\DomainEventInterface;
use PHPUnit\Framework\TestCase;

final class AggregateRootTest extends TestCase
{
    public function testRecordsDomainEvents(): void
    {
        $aggregate = new TestAggregate();

        self::assertCount(0, $aggregate->recordedDomainEvents());

        $aggregate->doSomething();

        $events = $aggregate->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(TestEvent::class, $events[0]);
    }

    public function testPullDomainEventsClearsRecordedEvents(): void
    {
        $aggregate = new TestAggregate();
        $aggregate->doSomething();

        self::assertCount(1, $aggregate->recordedDomainEvents());

        $pulledEvents = $aggregate->pullDomainEvents();

        self::assertCount(1, $pulledEvents);
        self::assertCount(0, $aggregate->recordedDomainEvents());
    }

    public function testPullDomainEventsCanBeCalledMultipleTimes(): void
    {
        $aggregate = new TestAggregate();
        $aggregate->doSomething();

        $firstPull = $aggregate->pullDomainEvents();
        self::assertCount(1, $firstPull);

        $secondPull = $aggregate->pullDomainEvents();
        self::assertCount(0, $secondPull);
    }

    public function testRecordsMultipleEvents(): void
    {
        $aggregate = new TestAggregate();

        $aggregate->doSomething();
        $aggregate->doSomethingElse();

        $events = $aggregate->recordedDomainEvents();
        self::assertCount(2, $events);
    }
}

// Test fixtures

final class TestAggregate extends AggregateRoot
{
    public function doSomething(): void
    {
        $this->recordDomainEvent(new TestEvent('event-1', 'aggregate-1'));
    }

    public function doSomethingElse(): void
    {
        $this->recordDomainEvent(new TestEvent('event-2', 'aggregate-1'));
    }
}

final class TestEvent implements DomainEventInterface
{
    public function __construct(
        private readonly string $eventId,
        private readonly string $aggregateId,
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
        return new \DateTimeImmutable();
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
