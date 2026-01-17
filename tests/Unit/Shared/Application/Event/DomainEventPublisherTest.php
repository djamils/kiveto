<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Application\Event;

use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Event\DomainEventInterface;
use PHPUnit\Framework\TestCase;

final class DomainEventPublisherTest extends TestCase
{
    public function testPublishesEventsFromAggregateAndClearsThem(): void
    {
        $event1 = $this->createEvent('agg-1', 'event.one.v1');
        $event2 = $this->createEvent('agg-1', 'event.two.v1');

        $aggregate = new class($event1, $event2) extends AggregateRoot {
            public function __construct(
                DomainEventInterface $event1,
                DomainEventInterface $event2,
            ) {
                $this->recordDomainEvent($event1);
                $this->recordDomainEvent($event2);
            }
        };

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())
            ->method('publish')
            ->with([], $event1, $event2)
        ;

        $publisher = new DomainEventPublisher($eventBus);
        $publisher->publish($aggregate);

        self::assertSame([], $aggregate->recordedDomainEvents());
    }

    public function testDoesNotPublishWhenNoEvents(): void
    {
        $aggregate = new class extends AggregateRoot {
        };

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::never())
            ->method('publish')
        ;

        $publisher = new DomainEventPublisher($eventBus);
        $publisher->publish($aggregate);
    }

    public function testPublishesSingleEvent(): void
    {
        $event = $this->createEvent('agg-1', 'event.single.v1');

        $aggregate = new class($event) extends AggregateRoot {
            public function __construct(DomainEventInterface $event)
            {
                $this->recordDomainEvent($event);
            }
        };

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())
            ->method('publish')
            ->with([], $event)
        ;

        $publisher = new DomainEventPublisher($eventBus);
        $publisher->publish($aggregate);
    }

    public function testPublishMultipleTimesWithSameAggregate(): void
    {
        $event1 = $this->createEvent('agg-1', 'event.first.v1');
        $event2 = $this->createEvent('agg-1', 'event.second.v1');

        $aggregate = new class($event1, $event2) extends AggregateRoot {
            public function __construct(
                private readonly DomainEventInterface $event1,
                private readonly DomainEventInterface $event2,
            ) {
            }

            public function doSomething(): void
            {
                $this->recordDomainEvent($this->event1);
            }

            public function doSomethingElse(): void
            {
                $this->recordDomainEvent($this->event2);
            }
        };

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::exactly(2))
            ->method('publish')
            ->willReturnCallback(function (array $stamps, object ...$events) use ($event1, $event2): void {
                /** @var int $call */
                static $call = 0;
                ++$call;

                if (1 === $call) {
                    self::assertSame([], $stamps);
                    self::assertSame([$event1], $events);
                } elseif (2 === $call) {
                    self::assertSame([], $stamps);
                    self::assertSame([$event2], $events);
                }
            })
        ;

        $publisher = new DomainEventPublisher($eventBus);

        $aggregate->doSomething();
        $publisher->publish($aggregate);
        self::assertSame([], $aggregate->recordedDomainEvents());

        $aggregate->doSomethingElse();
        $publisher->publish($aggregate);
        self::assertSame([], $aggregate->recordedDomainEvents());
    }

    private function createEvent(string $aggregateId, string $name): DomainEventInterface
    {
        return new class($aggregateId, $name) implements DomainEventInterface {
            public function __construct(
                private readonly string $aggregateId,
                private readonly string $name,
            ) {
            }

            public function aggregateId(): string
            {
                return $this->aggregateId;
            }

            public function name(): string
            {
                return $this->name;
            }

            public function payload(): array
            {
                return ['test' => 'data'];
            }
        };
    }
}
