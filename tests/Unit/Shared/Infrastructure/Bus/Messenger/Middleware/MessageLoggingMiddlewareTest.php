<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Bus\Messenger\Middleware;

use App\Shared\Application\Bus\CommandInterface;
use App\Shared\Application\Bus\QueryInterface;
use App\Shared\Domain\Event\DomainEventInterface;
use App\Shared\Domain\Event\IntegrationEventInterface;
use App\Shared\Infrastructure\Bus\Messenger\Middleware\MessageLoggingMiddleware;
use App\Shared\Infrastructure\Bus\Messenger\Stamp\MessageMetadataStamp;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

final class MessageLoggingMiddlewareTest extends TestCase
{
    public function testLogsMessageProcessingWithMetadata(): void
    {
        $loggedMessages = [];
        $logger         = $this->createLogger($loggedMessages);

        $middleware = new MessageLoggingMiddleware($logger);

        $occurredAt = new \DateTimeImmutable('2025-01-05 10:00:00');
        $stamp      = new MessageMetadataStamp(
            messageId: 'msg-123',
            occurredAt: $occurredAt,
            correlationId: 'corr-456',
            causationId: 'cause-789',
            actorId: 'user-999',
        );

        $message  = new \stdClass();
        $envelope = new Envelope($message, [$stamp]);

        $stack = $this->createTestStack($envelope);

        $middleware->handle($envelope, $stack);

        self::assertCount(2, $loggedMessages);

        self::assertSame('info', $loggedMessages[0]['level']);
        self::assertSame('Processing message', $loggedMessages[0]['message']);
        self::assertSame(\stdClass::class, $loggedMessages[0]['context']['messageClass']);
        self::assertSame('other', $loggedMessages[0]['context']['messageType']);
        self::assertSame('msg-123', $loggedMessages[0]['context']['messageId']);
        self::assertSame('corr-456', $loggedMessages[0]['context']['correlationId']);
        self::assertSame('cause-789', $loggedMessages[0]['context']['causationId']);
        self::assertSame($occurredAt->format(\DateTimeInterface::ATOM), $loggedMessages[0]['context']['occurredAt']);
        self::assertSame('user-999', $loggedMessages[0]['context']['actorId']);

        self::assertSame('info', $loggedMessages[1]['level']);
        self::assertSame('Message processed successfully', $loggedMessages[1]['message']);
        self::assertArrayHasKey('duration', $loggedMessages[1]['context']);
        $duration = $loggedMessages[1]['context']['duration'];
        self::assertIsString($duration);
        self::assertMatchesRegularExpression('/^\d+(\.\d+)? ms$/', $duration);
    }

    public function testLogsMessageProcessingWithoutMetadata(): void
    {
        $loggedMessages = [];
        $logger         = $this->createLogger($loggedMessages);

        $middleware = new MessageLoggingMiddleware($logger);

        $message  = new \stdClass();
        $envelope = new Envelope($message);

        $stack = $this->createTestStack($envelope);

        $middleware->handle($envelope, $stack);

        self::assertCount(2, $loggedMessages);

        self::assertNull($loggedMessages[0]['context']['messageId']);
        self::assertNull($loggedMessages[0]['context']['correlationId']);
        self::assertNull($loggedMessages[0]['context']['causationId']);
        self::assertNull($loggedMessages[0]['context']['occurredAt']);
        self::assertNull($loggedMessages[0]['context']['actorId']);
    }

    public function testLogsErrorWhenExceptionOccurs(): void
    {
        $loggedMessages = [];
        $logger         = $this->createLogger($loggedMessages);

        $middleware = new MessageLoggingMiddleware($logger);

        $occurredAt = new \DateTimeImmutable('2025-01-05 10:00:00');
        $stamp      = new MessageMetadataStamp(
            messageId: 'msg-123',
            occurredAt: $occurredAt,
            correlationId: 'corr-456',
            causationId: null,
        );

        $message  = new \stdClass();
        $envelope = new Envelope($message, [$stamp]);

        $exception = new \RuntimeException('Handler failed');
        $stack     = $this->createTestStack($envelope, $exception);

        try {
            $middleware->handle($envelope, $stack);
            self::fail('Expected exception to be thrown');
        } catch (\RuntimeException $e) {
            self::assertSame($exception, $e);
        }

        self::assertCount(2, $loggedMessages);

        self::assertSame('info', $loggedMessages[0]['level']);
        self::assertSame('Processing message', $loggedMessages[0]['message']);

        self::assertSame('error', $loggedMessages[1]['level']);
        self::assertSame('Message processing failed', $loggedMessages[1]['message']);
        self::assertArrayHasKey('duration', $loggedMessages[1]['context']);
        $duration = $loggedMessages[1]['context']['duration'];
        self::assertIsString($duration);
        self::assertMatchesRegularExpression('/^\d+(\.\d+)? ms$/', $duration);
        self::assertSame(\RuntimeException::class, $loggedMessages[1]['context']['exception']);
        self::assertSame('Handler failed', $loggedMessages[1]['context']['exceptionMessage']);
    }

    public function testReturnsEnvelopeFromNextHandler(): void
    {
        $loggedMessages = [];
        $logger         = $this->createLogger($loggedMessages);

        $middleware = new MessageLoggingMiddleware($logger);

        $message          = new \stdClass();
        $originalEnvelope = new Envelope($message);
        $modifiedEnvelope = new Envelope($message, [new MessageMetadataStamp(
            messageId: 'new-msg',
            occurredAt: new \DateTimeImmutable(),
            correlationId: null,
            causationId: null,
        )]);

        $stack = $this->createTestStack($modifiedEnvelope);

        $result = $middleware->handle($originalEnvelope, $stack);

        self::assertSame($modifiedEnvelope, $result);
    }

    public function testMeasuresDurationAccurately(): void
    {
        $loggedMessages = [];
        $logger         = $this->createLogger($loggedMessages);

        $middleware = new MessageLoggingMiddleware($logger);

        $message  = new \stdClass();
        $envelope = new Envelope($message);

        $stack = $this->createTestStack($envelope, null, function (): void {
            usleep(10000); // 10ms
        });

        $middleware->handle($envelope, $stack);

        self::assertCount(2, $loggedMessages);

        $duration = $loggedMessages[1]['context']['duration'];
        self::assertIsString($duration);
        $matches = [];
        preg_match('/^(\d+(?:\.\d+)?) ms$/', $duration, $matches);

        self::assertNotEmpty($matches);
        $durationMs = (float) $matches[1];

        self::assertGreaterThanOrEqual(9.0, $durationMs);
    }

    public function testResolvesMessageTypeForCommand(): void
    {
        $loggedMessages = [];
        $logger         = $this->createLogger($loggedMessages);
        $middleware     = new MessageLoggingMiddleware($logger);

        $command = new class implements CommandInterface {
        };
        $envelope = new Envelope($command);
        $stack    = $this->createTestStack($envelope);

        $middleware->handle($envelope, $stack);

        self::assertSame('command', $loggedMessages[0]['context']['messageType']);
    }

    public function testResolvesMessageTypeForQuery(): void
    {
        $loggedMessages = [];
        $logger         = $this->createLogger($loggedMessages);
        $middleware     = new MessageLoggingMiddleware($logger);

        $query = new class implements QueryInterface {
        };
        $envelope = new Envelope($query);
        $stack    = $this->createTestStack($envelope);

        $middleware->handle($envelope, $stack);

        self::assertSame('query', $loggedMessages[0]['context']['messageType']);
    }

    public function testResolvesMessageTypeForDomainEvent(): void
    {
        $loggedMessages = [];
        $logger         = $this->createLogger($loggedMessages);
        $middleware     = new MessageLoggingMiddleware($logger);

        $event = new class implements DomainEventInterface {
            public function aggregateId(): string
            {
                return 'test-123';
            }

            public function name(): string
            {
                return 'test.event.v1';
            }

            public function payload(): array
            {
                return [];
            }
        };
        $envelope = new Envelope($event);
        $stack    = $this->createTestStack($envelope);

        $middleware->handle($envelope, $stack);

        self::assertSame('domain_event', $loggedMessages[0]['context']['messageType']);
    }

    public function testResolvesMessageTypeForIntegrationEvent(): void
    {
        $loggedMessages = [];
        $logger         = $this->createLogger($loggedMessages);
        $middleware     = new MessageLoggingMiddleware($logger);

        $event = new class implements IntegrationEventInterface {
            public function aggregateId(): string
            {
                return 'test-123';
            }

            public function name(): string
            {
                return 'test.integration.event.v1';
            }

            public function payload(): array
            {
                return [];
            }
        };
        $envelope = new Envelope($event);
        $stack    = $this->createTestStack($envelope);

        $middleware->handle($envelope, $stack);

        self::assertSame('integration_event', $loggedMessages[0]['context']['messageType']);
    }

    public function testResolvesMessageTypeForOther(): void
    {
        $loggedMessages = [];
        $logger         = $this->createLogger($loggedMessages);
        $middleware     = new MessageLoggingMiddleware($logger);

        $message  = new \stdClass();
        $envelope = new Envelope($message);
        $stack    = $this->createTestStack($envelope);

        $middleware->handle($envelope, $stack);

        self::assertSame('other', $loggedMessages[0]['context']['messageType']);
    }

    public function testMessageTypeResolutionPriority(): void
    {
        $loggedMessages = [];
        $logger         = $this->createLogger($loggedMessages);
        $middleware     = new MessageLoggingMiddleware($logger);

        // Command takes priority over DomainEvent
        $commandEvent = new class implements CommandInterface, DomainEventInterface {
            public function aggregateId(): string
            {
                return 'test-123';
            }

            public function name(): string
            {
                return 'test.command.event.v1';
            }

            public function payload(): array
            {
                return [];
            }
        };
        $envelope = new Envelope($commandEvent);
        $stack    = $this->createTestStack($envelope);

        $middleware->handle($envelope, $stack);

        self::assertSame('command', $loggedMessages[0]['context']['messageType']);
    }

    /**
     * @param array<array{level: string, message: string, context: array<string, mixed>}> $loggedMessages
     */
    private function createLogger(array &$loggedMessages): LoggerInterface
    {
        return new class($loggedMessages) implements LoggerInterface {
            /**
             * @param array<array{level: string, message: string, context: array<string, mixed>}> $loggedMessages
             *
             * @phpstan-ignore-next-line property.onlyWritten
             */
            public function __construct(private array &$loggedMessages)
            {
            }

            /**
             * @param array<string, mixed> $context
             */
            public function emergency(string|\Stringable $message, array $context = []): void
            {
                $this->log('emergency', $message, $context);
            }

            /**
             * @param array<string, mixed> $context
             */
            public function alert(string|\Stringable $message, array $context = []): void
            {
                $this->log('alert', $message, $context);
            }

            /**
             * @param array<string, mixed> $context
             */
            public function critical(string|\Stringable $message, array $context = []): void
            {
                $this->log('critical', $message, $context);
            }

            /**
             * @param array<string, mixed> $context
             */
            public function error(string|\Stringable $message, array $context = []): void
            {
                $this->log('error', $message, $context);
            }

            /**
             * @param array<string, mixed> $context
             */
            public function warning(string|\Stringable $message, array $context = []): void
            {
                $this->log('warning', $message, $context);
            }

            /**
             * @param array<string, mixed> $context
             */
            public function notice(string|\Stringable $message, array $context = []): void
            {
                $this->log('notice', $message, $context);
            }

            /**
             * @param array<string, mixed> $context
             */
            public function info(string|\Stringable $message, array $context = []): void
            {
                $this->log('info', $message, $context);
            }

            /**
             * @param array<string, mixed> $context
             */
            public function debug(string|\Stringable $message, array $context = []): void
            {
                $this->log('debug', $message, $context);
            }

            /**
             * @param array<string, mixed> $context
             */
            public function log($level, string|\Stringable $message, array $context = []): void
            {
                \assert(\is_string($level));
                $this->loggedMessages[] = [
                    'level'   => $level,
                    'message' => (string) $message,
                    'context' => $context,
                ];
            }
        };
    }

    private function createTestStack(
        Envelope $envelope,
        ?\Throwable $exceptionToThrow = null,
        ?callable $beforeReturn = null,
    ): StackInterface {
        return new class($envelope, $exceptionToThrow, $beforeReturn) implements StackInterface {
            /**
             * @param callable(): void|null $beforeReturn
             */
            public function __construct(
                private Envelope $envelope,
                private ?\Throwable $exceptionToThrow,
                private $beforeReturn,
            ) {
            }

            public function next(): MiddlewareInterface
            {
                // phpcs:ignore
                return new class($this->envelope, $this->exceptionToThrow, $this->beforeReturn) implements MiddlewareInterface {
                    /**
                     * @param callable(): void|null $beforeReturn
                     */
                    public function __construct(
                        private Envelope $envelope,
                        private ?\Throwable $exceptionToThrow,
                        private $beforeReturn,
                    ) {
                    }

                    public function handle(Envelope $envelope, StackInterface $stack): Envelope
                    {
                        $callback = $this->beforeReturn;
                        if (null !== $callback) {
                            $callback();
                        }

                        if (null !== $this->exceptionToThrow) {
                            throw $this->exceptionToThrow;
                        }

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
