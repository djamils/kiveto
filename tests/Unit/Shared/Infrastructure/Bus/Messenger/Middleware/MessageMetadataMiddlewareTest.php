<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Bus\Messenger\Middleware;

use App\Shared\Application\Messaging\MessageContext;
use App\Shared\Application\Security\ActorContext;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Infrastructure\Bus\Messenger\Middleware\MessageMetadataMiddleware;
use App\Shared\Infrastructure\Bus\Messenger\Stamp\MessageMetadataStamp;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

final class MessageMetadataMiddlewareTest extends TestCase
{
    public function testAddsMetadataStampWhenAbsent(): void
    {
        $uuidGenerator = $this->createMock(UuidGeneratorInterface::class);
        $uuidGenerator->expects(self::exactly(2))
            ->method('generate')
            ->willReturnOnConsecutiveCalls('msg-123', 'corr-456')
        ;

        $clock      = $this->createMock(ClockInterface::class);
        $occurredAt = new \DateTimeImmutable('2025-01-05 10:00:00');
        $clock->expects(self::once())
            ->method('now')
            ->willReturn($occurredAt)
        ;

        $messageContext = new MessageContext();
        $actorContext   = new ActorContext();
        $actorContext->set('user-789');

        $middleware = new MessageMetadataMiddleware(
            $uuidGenerator,
            $clock,
            $messageContext,
            $actorContext,
        );

        $message  = new \stdClass();
        $envelope = new Envelope($message);

        $stack = new class($envelope, $occurredAt) implements StackInterface {
            public function __construct(
                private Envelope $envelope,
                private \DateTimeImmutable $occurredAt,
            ) {
            }

            public function next(): MiddlewareInterface
            {
                return new class($this->envelope, $this->occurredAt) implements MiddlewareInterface {
                    public function __construct(
                        private Envelope $envelope,
                        private \DateTimeImmutable $occurredAt,
                    ) {
                    }

                    public function handle(Envelope $envelope, StackInterface $stack): Envelope
                    {
                        $stamp = $envelope->last(MessageMetadataStamp::class);
                        TestCase::assertInstanceOf(MessageMetadataStamp::class, $stamp);
                        TestCase::assertSame('msg-123', $stamp->messageId);
                        TestCase::assertSame($this->occurredAt, $stamp->occurredAt);
                        TestCase::assertSame('corr-456', $stamp->correlationId);
                        TestCase::assertNull($stamp->causationId);
                        TestCase::assertSame('user-789', $stamp->actorId);

                        return $this->envelope;
                    }
                };
            }

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                return $this->envelope;
            }
        };

        $middleware->handle($envelope, $stack);
    }

    public function testDoesNotOverrideExistingStamp(): void
    {
        $uuidGenerator = $this->createMock(UuidGeneratorInterface::class);
        $uuidGenerator->expects(self::never())->method('generate');

        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::never())->method('now');

        $messageContext = new MessageContext();
        $actorContext   = new ActorContext();

        $middleware = new MessageMetadataMiddleware(
            $uuidGenerator,
            $clock,
            $messageContext,
            $actorContext,
        );

        $existingStamp = new MessageMetadataStamp(
            messageId: 'existing-msg',
            occurredAt: new \DateTimeImmutable('2025-01-05 09:00:00'),
            correlationId: 'existing-corr',
            causationId: null,
            actorId: 'existing-user',
        );

        $message  = new \stdClass();
        $envelope = new Envelope($message, [$existingStamp]);

        $stack = new class($envelope, $existingStamp) implements StackInterface {
            public function __construct(
                private Envelope $envelope,
                private MessageMetadataStamp $existingStamp,
            ) {
            }

            public function next(): MiddlewareInterface
            {
                return new class($this->envelope, $this->existingStamp) implements MiddlewareInterface {
                    public function __construct(
                        private Envelope $envelope,
                        private MessageMetadataStamp $existingStamp,
                    ) {
                    }

                    public function handle(Envelope $envelope, StackInterface $stack): Envelope
                    {
                        $stamp = $envelope->last(MessageMetadataStamp::class);
                        TestCase::assertSame($this->existingStamp, $stamp);

                        return $this->envelope;
                    }
                };
            }

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                return $this->envelope;
            }
        };

        $middleware->handle($envelope, $stack);
    }

    public function testDerivesCorrelationIdAndCausationIdFromContext(): void
    {
        $uuidGenerator = $this->createMock(UuidGeneratorInterface::class);
        $uuidGenerator->expects(self::once())
            ->method('generate')
            ->willReturn('msg-nested')
        ;

        $clock      = $this->createMock(ClockInterface::class);
        $occurredAt = new \DateTimeImmutable('2025-01-05 10:00:00');
        $clock->expects(self::once())
            ->method('now')
            ->willReturn($occurredAt)
        ;

        $messageContext = new MessageContext();
        $parentStamp    = new MessageMetadataStamp(
            messageId: 'msg-parent',
            occurredAt: new \DateTimeImmutable('2025-01-05 09:00:00'),
            correlationId: 'corr-root',
            causationId: null,
        );
        $messageContext->push($parentStamp);

        $actorContext = new ActorContext();

        $middleware = new MessageMetadataMiddleware(
            $uuidGenerator,
            $clock,
            $messageContext,
            $actorContext,
        );

        $message  = new \stdClass();
        $envelope = new Envelope($message);

        $stack = new class($envelope) implements StackInterface {
            public function __construct(private Envelope $envelope)
            {
            }

            public function next(): MiddlewareInterface
            {
                return new class($this->envelope) implements MiddlewareInterface {
                    public function __construct(private Envelope $envelope)
                    {
                    }

                    public function handle(Envelope $envelope, StackInterface $stack): Envelope
                    {
                        $stamp = $envelope->last(MessageMetadataStamp::class);
                        TestCase::assertInstanceOf(MessageMetadataStamp::class, $stamp);
                        TestCase::assertSame('msg-nested', $stamp->messageId);
                        TestCase::assertSame('corr-root', $stamp->correlationId);
                        TestCase::assertSame('msg-parent', $stamp->causationId);

                        return $this->envelope;
                    }
                };
            }

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                return $this->envelope;
            }
        };

        $middleware->handle($envelope, $stack);
    }
}
