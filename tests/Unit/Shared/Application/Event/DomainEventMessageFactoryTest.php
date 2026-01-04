<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Application\Event;

use App\Shared\Application\Event\DomainEventMessageFactory;
use App\Shared\Domain\Event\DomainEventInterface;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Tests\Shared\Time\FrozenClock;
use PHPUnit\Framework\TestCase;

final class DomainEventMessageFactoryTest extends TestCase
{
    public function testWrapAddsMetadata(): void
    {
        $uuidGenerator = $this->createStub(UuidGeneratorInterface::class);
        $uuidGenerator->method('generate')->willReturn('00000000-0000-0000-0000-000000000001');

        $fixedTime = new \DateTimeImmutable('2025-01-01T12:00:00+00:00');
        $clock     = new FrozenClock($fixedTime);

        $factory = new DomainEventMessageFactory($uuidGenerator);

        $event = new class implements DomainEventInterface {
            public function aggregateId(): string
            {
                return 'agg-1';
            }

            public function type(): string
            {
                return 'test.aggregate.happened.v1';
            }

            public function payload(): array
            {
                return ['foo' => 'bar'];
            }
        };

        $message = $factory->wrap($event, $clock->now());

        self::assertSame('00000000-0000-0000-0000-000000000001', $message->eventId());
        self::assertSame($fixedTime, $message->occurredAt());
        self::assertSame($event, $message->event());
    }
}
