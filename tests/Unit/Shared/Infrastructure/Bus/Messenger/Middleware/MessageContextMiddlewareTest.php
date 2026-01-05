<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Bus\Messenger\Middleware;

use App\Shared\Application\Messaging\MessageContext;
use App\Shared\Infrastructure\Bus\Messenger\Middleware\MessageContextMiddleware;
use App\Shared\Infrastructure\Bus\Messenger\Stamp\MessageMetadataStamp;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

final class MessageContextMiddlewareTest extends TestCase
{
    public function testPushesAndPopsMetadataFromContext(): void
    {
        $messageContext = new MessageContext();

        $middleware = new MessageContextMiddleware($messageContext);

        $stamp = new MessageMetadataStamp(
            messageId: 'msg-123',
            occurredAt: new \DateTimeImmutable('2025-01-05 10:00:00'),
            correlationId: 'corr-456',
            causationId: null,
        );

        $message  = new \stdClass();
        $envelope = new Envelope($message, [$stamp]);

        $capturedMessageId = null;
        $handlerCalled     = false;

        $innerHandler = function () use ($messageContext, &$capturedMessageId, &$handlerCalled): void {
            $capturedMessageId = $messageContext->messageId();
            $handlerCalled     = true;
        };

        $stack = $this->createTestStack($envelope, $innerHandler);

        $middleware->handle($envelope, $stack);

        self::assertTrue($handlerCalled);
        self::assertSame('msg-123', $capturedMessageId);

        // After handle, context should be empty
        $this->expectException(\LogicException::class);
        $messageContext->current();
    }

    public function testPopsEvenWhenExceptionOccurs(): void
    {
        $messageContext = new MessageContext();

        $middleware = new MessageContextMiddleware($messageContext);

        $stamp = new MessageMetadataStamp(
            messageId: 'msg-123',
            occurredAt: new \DateTimeImmutable('2025-01-05 10:00:00'),
            correlationId: 'corr-456',
            causationId: null,
        );

        $message  = new \stdClass();
        $envelope = new Envelope($message, [$stamp]);

        $exception = new \RuntimeException('Handler failed');

        $stack = $this->createTestStack($envelope, static function () use ($exception): void {
            throw $exception;
        });

        try {
            $middleware->handle($envelope, $stack);
            self::fail('Expected exception to be thrown');
        } catch (\RuntimeException $e) {
            self::assertSame($exception, $e);
        }

        // Context should be popped even after exception
        $this->expectException(\LogicException::class);
        $messageContext->current();
    }

    public function testDoesNothingWhenNoStamp(): void
    {
        $messageContext = new MessageContext();

        $middleware = new MessageContextMiddleware($messageContext);

        $message  = new \stdClass();
        $envelope = new Envelope($message);

        $exceptionThrown = false;
        $handlerCalled   = false;

        $innerHandler = function () use ($messageContext, &$exceptionThrown, &$handlerCalled): void {
            try {
                $messageContext->current();
            } catch (\LogicException) {
                $exceptionThrown = true;
            }
            $handlerCalled = true;
        };

        $stack = $this->createTestStack($envelope, $innerHandler);

        $middleware->handle($envelope, $stack);

        self::assertTrue($handlerCalled);
        self::assertTrue($exceptionThrown, 'Expected LogicException to be thrown when context is empty');
    }

    private function createTestStack(Envelope $envelope, callable $handler): StackInterface
    {
        return new class($envelope, $handler) implements StackInterface {
            /**
             * @param callable(): void $handler
             */
            public function __construct(
                private Envelope $envelope,
                private $handler,
            ) {
            }

            public function next(): MiddlewareInterface
            {
                return new class($this->envelope, $this->handler) implements MiddlewareInterface {
                    /**
                     * @param callable(): void $handler
                     */
                    public function __construct(
                        private Envelope $envelope,
                        private $handler,
                    ) {
                    }

                    public function handle(Envelope $envelope, StackInterface $stack): Envelope
                    {
                        ($this->handler)();

                        return $this->envelope;
                    }
                };
            }

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                return $this->envelope;
            }
        };
    }
}
