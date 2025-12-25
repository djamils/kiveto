<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Application\Event;

use App\Shared\Application\Event\DomainEventFactory;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Tests\Unit\Shared\Application\Event\Fixture\FactoryEvent;
use PHPUnit\Framework\TestCase;

final class DomainEventFactoryTest extends TestCase
{
    public function testCreatesEventWithGeneratedMetadata(): void
    {
        $expectedEventId = '01234567-89ab-cdef-0123-456789abcdef';
        $expectedOccurredAt = new \DateTimeImmutable('2025-01-01 12:00:00');

        $uuidGenerator = $this->createStub(UuidGeneratorInterface::class);
        $uuidGenerator->method('generate')->willReturn($expectedEventId);

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($expectedOccurredAt);

        $factory = new DomainEventFactory($uuidGenerator, $clock);

        $event = $factory->create(FactoryEvent::class, [
            'userId' => 'user-123',
            'email' => 'test@example.com',
        ]);

        self::assertInstanceOf(FactoryEvent::class, $event);
        self::assertSame($expectedEventId, $event->eventId());
        self::assertSame($expectedOccurredAt, $event->occurredAt());
        self::assertSame('user-123', $event->aggregateId());
        self::assertSame(['userId' => 'user-123', 'email' => 'test@example.com'], $event->payload());
    }
}
