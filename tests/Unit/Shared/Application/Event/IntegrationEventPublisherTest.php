<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Application\Event;

use App\Shared\Application\Bus\IntegrationEventBusInterface;
use App\Shared\Application\Event\IntegrationEventPublisher;
use PHPUnit\Framework\TestCase;

final class IntegrationEventPublisherTest extends TestCase
{
    public function testPublishesMultipleEvents(): void
    {
        $event1 = new \stdClass();
        $event2 = new \stdClass();

        $integrationEventBus = $this->createMock(IntegrationEventBusInterface::class);
        $integrationEventBus->expects(self::once())
            ->method('publish')
            ->with([], $event1, $event2)
        ;

        $publisher = new IntegrationEventPublisher($integrationEventBus);
        $publisher->publish($event1, $event2);
    }

    public function testDoesNotPublishWhenNoEvents(): void
    {
        $integrationEventBus = $this->createMock(IntegrationEventBusInterface::class);
        $integrationEventBus->expects(self::never())
            ->method('publish')
        ;

        $publisher = new IntegrationEventPublisher($integrationEventBus);
        $publisher->publish();
    }

    public function testPublishesSingleEvent(): void
    {
        $event = new \stdClass();

        $integrationEventBus = $this->createMock(IntegrationEventBusInterface::class);
        $integrationEventBus->expects(self::once())
            ->method('publish')
            ->with([], $event)
        ;

        $publisher = new IntegrationEventPublisher($integrationEventBus);
        $publisher->publish($event);
    }

    public function testPublishesWithEmptyStampsArray(): void
    {
        $event = new \stdClass();

        $integrationEventBus = $this->createMock(IntegrationEventBusInterface::class);
        $integrationEventBus->expects(self::once())
            ->method('publish')
            ->with(
                self::callback(function (array $stamps): bool {
                    return [] === $stamps;
                }),
                $event,
            )
        ;

        $publisher = new IntegrationEventPublisher($integrationEventBus);
        $publisher->publish($event);
    }

    public function testPublishesEventsInCorrectOrder(): void
    {
        $event1     = new \stdClass();
        $event1->id = 1;
        $event2     = new \stdClass();
        $event2->id = 2;
        $event3     = new \stdClass();
        $event3->id = 3;

        $integrationEventBus = $this->createMock(IntegrationEventBusInterface::class);
        $integrationEventBus->expects(self::once())
            ->method('publish')
            ->with(
                [],
                self::callback(function (object $e) use ($event1): bool {
                    return $e === $event1;
                }),
                self::callback(function (object $e) use ($event2): bool {
                    return $e === $event2;
                }),
                self::callback(function (object $e) use ($event3): bool {
                    return $e === $event3;
                }),
            )
        ;

        $publisher = new IntegrationEventPublisher($integrationEventBus);
        $publisher->publish($event1, $event2, $event3);
    }

    public function testPublishMultipleTimes(): void
    {
        $event1 = new \stdClass();
        $event2 = new \stdClass();

        $integrationEventBus = $this->createMock(IntegrationEventBusInterface::class);
        $integrationEventBus->expects(self::exactly(2))
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

        $publisher = new IntegrationEventPublisher($integrationEventBus);

        $publisher->publish($event1);
        $publisher->publish($event2);
    }

    public function testPublishDoesNotModifyEvents(): void
    {
        $event        = new \stdClass();
        $event->data  = 'test-data';
        $originalData = $event->data;

        $integrationEventBus = $this->createMock(IntegrationEventBusInterface::class);
        $integrationEventBus->expects(self::once())
            ->method('publish')
            ->with([], $event)
        ;

        $publisher = new IntegrationEventPublisher($integrationEventBus);
        $publisher->publish($event);

        self::assertSame($originalData, $event->data);
    }
}
