<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Bus\Messenger;

use App\Shared\Infrastructure\Bus\Messenger\CommandBus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final class CommandBusTest extends TestCase
{
    public function testDispatchReturnsHandledResult(): void
    {
        $command      = new \stdClass();
        $handledStamp = new HandledStamp('ok', 'handler');

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(function (Envelope $envelope) use ($command): bool {
                return $envelope->getMessage() === $command;
            }))
            ->willReturn(new Envelope($command, [$handledStamp]))
        ;

        $commandBus = new CommandBus($bus);

        self::assertSame('ok', $commandBus->dispatch($command));
    }

    public function testDispatchRethrowsPreviousException(): void
    {
        $command  = new \stdClass();
        $inner    = new \RuntimeException('boom');
        $envelope = new Envelope($command);
        $failure  = new HandlerFailedException($envelope, [$inner]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())
            ->method('dispatch')
            ->willThrowException($failure)
        ;

        $commandBus = new CommandBus($bus);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom');

        $commandBus->dispatch($command);
    }
}
