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
        $event = new UserRegistered();

        self::assertSame('test-bc.user.registered.v1', $event->type());
    }

    public function testEventTypeHandlesSingleWordAction(): void
    {
        $event = new AnimalCreated();

        self::assertSame('test-bc.animal.created.v2', $event->type());
    }

    public function testEventTypeHandlesMultiWordAggregate(): void
    {
        $event = new InvoiceItemAdded();

        self::assertSame('test-bc.invoice-item.added.v1', $event->type());
    }

    public function testVersionIsIncludedInType(): void
    {
        $event = new AnimalCreated();

        self::assertStringContainsString('.v2', $event->type());
    }
}
