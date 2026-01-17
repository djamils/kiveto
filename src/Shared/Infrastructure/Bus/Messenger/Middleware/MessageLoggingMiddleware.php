<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus\Messenger\Middleware;

use App\Shared\Application\Bus\CommandInterface;
use App\Shared\Application\Bus\QueryInterface;
use App\Shared\Domain\Event\DomainEventInterface;
use App\Shared\Domain\Event\IntegrationEventInterface;
use App\Shared\Infrastructure\Bus\Messenger\Stamp\MessageMetadataStamp;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

/**
 * Middleware logging message processing with metadata.
 * Logs message type, metadata, duration, and success/failure.
 */
final readonly class MessageLoggingMiddleware implements MiddlewareInterface
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message      = $envelope->getMessage();
        $messageClass = $message::class;

        /** @var MessageMetadataStamp|null $stamp */
        $stamp = $envelope->last(MessageMetadataStamp::class);

        $context = [
            'messageClass'  => $messageClass,
            'messageType'   => $this->resolveMessageType($message),
            'messageId'     => $stamp?->messageId,
            'correlationId' => $stamp?->correlationId,
            'causationId'   => $stamp?->causationId,
            'occurredAt'    => $stamp?->occurredAt?->format(\DateTimeInterface::ATOM),
            'actorId'       => $stamp?->actorId,
        ];

        $startTime = microtime(true);

        try {
            $this->logger->info('Processing message', $context);

            $result = $stack->next()->handle($envelope, $stack);

            $duration            = microtime(true) - $startTime;
            $context['duration'] = round($duration * 1000, 2) . ' ms';

            $this->logger->info('Message processed successfully', $context);

            return $result;
        } catch (\Throwable $exception) {
            $duration                    = microtime(true) - $startTime;
            $context['duration']         = round($duration * 1000, 2) . ' ms';
            $context['exception']        = $exception::class;
            $context['exceptionMessage'] = $exception->getMessage();

            $this->logger->error('Message processing failed', $context);

            throw $exception;
        }
    }

    /**
     * Resolve message type based on implemented interfaces.
     * Priority order: command > query > integration_event > domain_event > other.
     */
    private function resolveMessageType(object $message): string
    {
        return match (true) {
            $message instanceof CommandInterface          => 'command',
            $message instanceof QueryInterface            => 'query',
            $message instanceof IntegrationEventInterface => 'integration_event',
            $message instanceof DomainEventInterface      => 'domain_event',
            default                                       => 'other',
        };
    }
}
