<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Application\Event;

use App\Shared\Application\Event\DomainEventFactory;
use App\Tests\Unit\Shared\Application\Event\Fixture\FactoryEvent;
use PHPUnit\Framework\TestCase;

final class DomainEventFactoryTest extends TestCase
{
    public function testCreatesEvent(): void
    {
        $factory = new DomainEventFactory();

        $event = $factory->create(FactoryEvent::class, [
            'user-123',
            'test@example.com',
        ]);

        self::assertInstanceOf(FactoryEvent::class, $event);
        self::assertSame('user-123', $event->aggregateId());
        self::assertSame(['userId' => 'user-123', 'email' => 'test@example.com'], $event->payload());
    }
}
