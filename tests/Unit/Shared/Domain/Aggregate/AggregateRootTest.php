<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Aggregate;

use App\Tests\Unit\Shared\Domain\Aggregate\Fixture\TestAggregate;
use App\Tests\Unit\Shared\Domain\Aggregate\Fixture\TestEvent;
use PHPUnit\Framework\TestCase;

final class AggregateRootTest extends TestCase
{
    public function testRecordsDomainEvents(): void
    {
        $aggregate = new TestAggregate();

        self::assertCount(0, $aggregate->recordedDomainEvents());

        $aggregate->doSomething();

        $events = $aggregate->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(TestEvent::class, $events[0]);
    }

    public function testPullDomainEventsClearsRecordedEvents(): void
    {
        $aggregate = new TestAggregate();
        $aggregate->doSomething();

        self::assertCount(1, $aggregate->recordedDomainEvents());

        $pulledEvents = $aggregate->pullDomainEvents();

        self::assertCount(1, $pulledEvents);
        self::assertCount(0, $aggregate->recordedDomainEvents());
    }

    public function testPullDomainEventsCanBeCalledMultipleTimes(): void
    {
        $aggregate = new TestAggregate();
        $aggregate->doSomething();

        $firstPull = $aggregate->pullDomainEvents();
        self::assertCount(1, $firstPull);

        $secondPull = $aggregate->pullDomainEvents();
        self::assertCount(0, $secondPull);
    }

    public function testRecordsMultipleEvents(): void
    {
        $aggregate = new TestAggregate();

        $aggregate->doSomething();
        $aggregate->doSomethingElse();

        $events = $aggregate->recordedDomainEvents();
        self::assertCount(2, $events);
    }

    public function testClearDomainEventsRemovesAllRecordedEvents(): void
    {
        $aggregate = new TestAggregate();

        $aggregate->doSomething();
        $aggregate->doSomethingElse();

        self::assertCount(2, $aggregate->recordedDomainEvents());

        $aggregate->clearEvents();

        self::assertCount(0, $aggregate->recordedDomainEvents());
    }
}
