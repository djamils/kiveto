<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Bus\Messenger\Handler;

use App\Shared\Application\Event\DomainEventMessageFactory;
use App\Shared\Domain\Event\DomainEventInterface;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Infrastructure\Bus\Messenger\Handler\IgnoreDomainEventMessageHandler;
use App\Tests\Shared\Time\FrozenClock;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class IgnoreDomainEventMessageHandlerTest extends TestCase
{
    private DomainEventMessageFactory $factory;
    private FrozenClock $clock;

    protected function setUp(): void
    {
        /** @var UuidGeneratorInterface&MockObject $uuid */
        $uuid = $this->createStub(UuidGeneratorInterface::class);
        $uuid->method('generate')->willReturn('018d3dcf-0000-7000-8000-000000000000');

        $this->clock   = new FrozenClock(new \DateTimeImmutable('2024-01-01T12:00:00Z'));
        $this->factory = new DomainEventMessageFactory($uuid);
    }

    public function testHandlerIsNoop(): void
    {
        $this->expectNotToPerformAssertions();

        $event = new class implements DomainEventInterface {
            public function aggregateId(): string
            {
                return 'aggregate-1';
            }

            public function type(): string
            {
                return 'test.event.v1';
            }

            public function payload(): array
            {
                return ['foo' => 'bar'];
            }
        };

        $message = $this->factory->wrap($event, $this->clock->now());
        $handler = new IgnoreDomainEventMessageHandler();

        // Should not throw and do nothing
        $handler($message);
    }
}
