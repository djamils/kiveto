<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Application\Event;

use App\Shared\Application\Event\DomainEventFactory;
use App\Shared\Domain\Event\AbstractDomainEvent;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\TestCase;

final class DomainEventFactoryTest extends TestCase
{
    public function testCreatesEventWithGeneratedMetadata(): void
    {
        $expectedEventId    = '01234567-89ab-cdef-0123-456789abcdef';
        $expectedOccurredAt = new \DateTimeImmutable('2025-01-01 12:00:00');

        $uuidGenerator = $this->createMock(UuidGeneratorInterface::class);
        $uuidGenerator->method('generate')->willReturn($expectedEventId);

        $clock = $this->createMock(ClockInterface::class);
        $clock->method('now')->willReturn($expectedOccurredAt);

        $factory = new DomainEventFactory($uuidGenerator, $clock);

        $event = $factory->create(
            TestFactoryEvent::class,
            'user-123',
            'test@example.com'
        );

        self::assertInstanceOf(TestFactoryEvent::class, $event);
        self::assertSame($expectedEventId, $event->eventId());
        self::assertSame($expectedOccurredAt, $event->occurredAt());
        self::assertSame('user-123', $event->aggregateId());
        self::assertSame(['userId' => 'user-123', 'email' => 'test@example.com'], $event->payload());
    }
}

// Test fixture

final class TestFactoryEvent extends AbstractDomainEvent
{
    protected const BOUNDED_CONTEXT = 'test';
    protected const VERSION         = 1;

    public function __construct(
        private readonly string $userId,
        private readonly string $email,
        string $eventId,
        \DateTimeImmutable $occurredAt,
    ) {
        parent::__construct($eventId, $occurredAt);
    }

    public function aggregateId(): string
    {
        return $this->userId;
    }

    public function payload(): array
    {
        return [
            'userId' => $this->userId,
            'email'  => $this->email,
        ];
    }
}
