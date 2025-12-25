<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Event;

use App\Tests\Unit\Shared\Domain\Event\Fixture\AnimalCreated;
use App\Tests\Unit\Shared\Domain\Event\Fixture\InvoiceItemAdded;
use App\Tests\Unit\Shared\Domain\Event\Fixture\UserRegistered;
use PHPUnit\Framework\TestCase;

final class DomainEventTest extends TestCase
{
    public function testEventTypeIsCorrectlyGeneratedFromClassName(): void
    {
        $eventId    = '01234567-89ab-cdef-0123-456789abcdef';
        $occurredAt = new \DateTimeImmutable('2025-01-01 12:00:00');

        $event = new UserRegistered($eventId, $occurredAt);

        self::assertSame('test-bc.user.registered.v1', $event->type());
    }

    public function testEventTypeHandlesSingleWordAction(): void
    {
        $eventId    = '01234567-89ab-cdef-0123-456789abcdef';
        $occurredAt = new \DateTimeImmutable('2025-01-01 12:00:00');

        $event = new AnimalCreated($eventId, $occurredAt);

        self::assertSame('test-bc.animal.created.v2', $event->type());
    }

    public function testEventTypeHandlesMultiWordAggregate(): void
    {
        $eventId    = '01234567-89ab-cdef-0123-456789abcdef';
        $occurredAt = new \DateTimeImmutable('2025-01-01 12:00:00');

        $event = new InvoiceItemAdded($eventId, $occurredAt);

        self::assertSame('test-bc.invoice-item.added.v1', $event->type());
    }

    public function testEventMetadataIsCorrectlyStored(): void
    {
        $eventId    = '01234567-89ab-cdef-0123-456789abcdef';
        $occurredAt = new \DateTimeImmutable('2025-01-01 12:00:00');

        $event = new UserRegistered($eventId, $occurredAt);

        self::assertSame($eventId, $event->eventId());
        self::assertSame($occurredAt, $event->occurredAt());
    }

    public function testVersionIsIncludedInType(): void
    {
        $eventId    = '01234567-89ab-cdef-0123-456789abcdef';
        $occurredAt = new \DateTimeImmutable('2025-01-01 12:00:00');

        $event = new AnimalCreated($eventId, $occurredAt);

        self::assertStringContainsString('.v2', $event->type());
    }
}
