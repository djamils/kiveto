<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Bus\Messenger;

use App\Shared\Infrastructure\Bus\Messenger\IntegrationEventBus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

final class IntegrationEventBusTest extends TestCase
{
    public function testPublishDispatchesAllEvents(): void
    {
        $event1 = new \stdClass();
        $event2 = new \stdClass();

        $dispatched = [];

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function (Envelope $envelope) use (&$dispatched): Envelope {
                $dispatched[] = $envelope->getMessage();

                return $envelope;
            })
        ;

        $integrationEventBus = new IntegrationEventBus($bus);

        $integrationEventBus->publish([], $event1, $event2);

        self::assertSame([$event1, $event2], $dispatched);
    }

    public function testPublishWithStampsAppliesStampsToAllEvents(): void
    {
        $event1 = new \stdClass();
        $event2 = new \stdClass();

        $stamps     = [new DelayStamp(5000)];
        $dispatched = [];

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function (Envelope $envelope) use (&$dispatched): Envelope {
                $dispatched[] = [
                    'message' => $envelope->getMessage(),
                    'stamps'  => $envelope->all(),
                ];

                return $envelope;
            })
        ;

        $integrationEventBus = new IntegrationEventBus($bus);

        $integrationEventBus->publish($stamps, $event1, $event2);

        self::assertCount(2, $dispatched);
        self::assertSame($event1, $dispatched[0]['message']);
        self::assertSame($event2, $dispatched[1]['message']);

        // Verify stamps are applied
        self::assertArrayHasKey(DelayStamp::class, $dispatched[0]['stamps']);
        self::assertArrayHasKey(DelayStamp::class, $dispatched[1]['stamps']);
    }

    public function testPublishWithNoEventsDoesNothing(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $integrationEventBus = new IntegrationEventBus($bus);

        $integrationEventBus->publish([]);
    }

    public function testPublishWithSingleEvent(): void
    {
        $event = new \stdClass();

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(function (Envelope $envelope) use ($event): bool {
                return $envelope->getMessage() === $event;
            }))
            ->willReturnArgument(0)
        ;

        $integrationEventBus = new IntegrationEventBus($bus);

        $integrationEventBus->publish([], $event);
    }
}
