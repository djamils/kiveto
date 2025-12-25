<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;
use PHPUnit\Framework\TestCase;

final class DomainEventTest extends TestCase
{
    public function testEventTypeIsCorrectlyGeneratedFromClassName(): void
    {
        $eventId    = '01234567-89ab-cdef-0123-456789abcdef';
        $occurredAt = new \DateTimeImmutable('2025-01-01 12:00:00');

        $event = new TestUserRegistered($eventId, $occurredAt);

        self::assertSame('test-bc.user.registered.v1', $event->type());
    }

    public function testEventTypeHandlesSingleWordAction(): void
    {
        $eventId    = '01234567-89ab-cdef-0123-456789abcdef';
        $occurredAt = new \DateTimeImmutable('2025-01-01 12:00:00');

        $event = new TestAnimalCreated($eventId, $occurredAt);

        self::assertSame('test-bc.animal.created.v2', $event->type());
    }

    public function testEventTypeHandlesMultiWordAggregate(): void
    {
        $eventId    = '01234567-89ab-cdef-0123-456789abcdef';
        $occurredAt = new \DateTimeImmutable('2025-01-01 12:00:00');

        $event = new TestInvoiceItemAdded($eventId, $occurredAt);

        self::assertSame('test-bc.invoice-item.added.v1', $event->type());
    }

    public function testEventMetadataIsCorrectlyStored(): void
    {
        $eventId    = '01234567-89ab-cdef-0123-456789abcdef';
        $occurredAt = new \DateTimeImmutable('2025-01-01 12:00:00');

        $event = new TestUserRegistered($eventId, $occurredAt);

        self::assertSame($eventId, $event->eventId());
        self::assertSame($occurredAt, $event->occurredAt());
    }

    public function testVersionIsIncludedInType(): void
    {
        $eventId    = '01234567-89ab-cdef-0123-456789abcdef';
        $occurredAt = new \DateTimeImmutable('2025-01-01 12:00:00');

        $event = new TestAnimalCreated($eventId, $occurredAt);

        self::assertStringContainsString('.v2', $event->type());
    }
}

// Test fixtures

final class TestUserRegistered extends AbstractDomainEvent
{
    protected const BOUNDED_CONTEXT = 'test-bc';
    protected const VERSION         = 1;

    public function aggregateId(): string
    {
        return 'user-123';
    }

    public function payload(): array
    {
        return [
            'email' => 'test@example.com',
        ];
    }
}

final class TestAnimalCreated extends AbstractDomainEvent
{
    protected const BOUNDED_CONTEXT = 'test-bc';
    protected const VERSION         = 2;

    public function aggregateId(): string
    {
        return 'animal-456';
    }

    public function payload(): array
    {
        return [
            'name'    => 'Rex',
            'species' => 'Dog',
        ];
    }
}

final class TestInvoiceItemAdded extends AbstractDomainEvent
{
    protected const BOUNDED_CONTEXT = 'test-bc';
    protected const VERSION         = 1;

    public function aggregateId(): string
    {
        return 'invoice-789';
    }

    public function payload(): array
    {
        return [
            'itemId' => 'item-001',
            'amount' => 50.00,
        ];
    }
}
