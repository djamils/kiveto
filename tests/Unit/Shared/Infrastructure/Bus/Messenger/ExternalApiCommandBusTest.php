<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Bus\Messenger;

use App\Shared\Application\Bus\ExternalApiCommandInterface;
use App\Shared\Infrastructure\Bus\Messenger\ExternalApiCommandBus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

#[CoversClass(ExternalApiCommandBus::class)]
final class ExternalApiCommandBusTest extends TestCase
{
    public function testTheCommandIsDispatchedOnTheGivenBusWithItsStamps(): void
    {
        $command = new class implements ExternalApiCommandInterface {
        };
        $stamp = new TransportNamesStamp(['async_external_batch']);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn (Envelope $envelope): bool => $envelope->getMessage() === $command
                && $envelope->last(TransportNamesStamp::class) === $stamp))
            ->willReturnCallback(static fn (Envelope $envelope): Envelope => $envelope)
        ;

        new ExternalApiCommandBus($bus)->send($command, $stamp);
    }
}
