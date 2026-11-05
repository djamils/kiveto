<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Bus\Messenger;

use App\Shared\Infrastructure\Bus\Messenger\QueryAnswerCache;
use App\Shared\Infrastructure\Bus\Messenger\QueryBus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final class QueryBusTest extends TestCase
{
    public function testAskReturnsHandledResult(): void
    {
        $query        = new \stdClass();
        $handledStamp = new HandledStamp('result', 'handler');

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(function (Envelope $envelope) use ($query): bool {
                return $envelope->getMessage() === $query;
            }))
            ->willReturn(new Envelope($query, [$handledStamp]))
        ;

        $queryBus = new QueryBus($bus);

        self::assertSame('result', $queryBus->ask($query));
    }

    public function testARequestCacheableQueryIsHandledOncePerRequest(): void
    {
        $handled = 0;
        $bus     = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(static function (Envelope $envelope) use (&$handled): Envelope {
            ++$handled;

            return $envelope->with(new HandledStamp('answer ' . $handled, 'handler'));
        });

        $answers  = new QueryAnswerCache();
        $queryBus = new QueryBus($bus, $answers);

        // The same values: one handling; other values: their own.
        self::assertSame('answer 1', $queryBus->ask(new CacheableProbe('clinic-1')));
        self::assertSame('answer 1', $queryBus->ask(new CacheableProbe('clinic-1')));
        self::assertSame('answer 2', $queryBus->ask(new CacheableProbe('clinic-2')));

        // Stamped, or not cacheable: always handled.
        self::assertSame('answer 3', $queryBus->ask(new CacheableProbe('clinic-1'), new DelayStamp(0)));
        self::assertSame('answer 4', $queryBus->ask(new \stdClass()));
        self::assertSame('answer 5', $queryBus->ask(new \stdClass()));

        // A command ran: forgotten.
        $answers->commandStarted();
        $answers->commandFinished();
        self::assertSame('answer 6', $queryBus->ask(new CacheableProbe('clinic-1')));

        // Without a cache, every query is handled.
        $uncached = new QueryBus($bus);
        self::assertSame('answer 7', $uncached->ask(new CacheableProbe('clinic-1')));
        self::assertSame('answer 8', $uncached->ask(new CacheableProbe('clinic-1')));
    }

    public function testAskRethrowsPreviousException(): void
    {
        $query    = new \stdClass();
        $inner    = new \RuntimeException('failure');
        $envelope = new Envelope($query);
        $failure  = new HandlerFailedException($envelope, [$inner]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())
            ->method('dispatch')
            ->willThrowException($failure)
        ;

        $queryBus = new QueryBus($bus);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('failure');

        $queryBus->ask($query);
    }
}
