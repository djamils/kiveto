<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Health;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/** A queue that counts its messages (none), or fails to. */
final class CountingQueue implements TransportInterface, MessageCountAwareInterface
{
    public int $counts = 0;

    public function __construct(private readonly ?\Throwable $failure)
    {
    }

    public function get(): iterable
    {
        return [];
    }

    public function ack(Envelope $envelope): void
    {
    }

    public function reject(Envelope $envelope): void
    {
    }

    public function send(Envelope $envelope): Envelope
    {
        return $envelope;
    }

    public function getMessageCount(): int
    {
        ++$this->counts;

        if (null !== $this->failure) {
            throw $this->failure;
        }

        return 0;
    }
}
