<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Bus\Messenger;

use App\Shared\Infrastructure\Bus\Messenger\QueryBus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
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
