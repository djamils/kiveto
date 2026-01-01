<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Bus\Messenger;

use App\Shared\Infrastructure\Bus\Messenger\EventBus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class EventBusTest extends TestCase
{
    public function testPublishDispatchesAllEvents(): void
    {
        $event1 = new \stdClass();
        $event2 = new \stdClass();

        $dispatched = [];

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function (object $event) use (&$dispatched): Envelope {
                $dispatched[] = $event;

                return new Envelope($event);
            })
        ;

        $eventBus = new EventBus($bus);

        $eventBus->publish($event1, $event2);

        self::assertSame([$event1, $event2], $dispatched);
    }
}
