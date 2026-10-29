<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Notification\Support;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * A Messenger bus that only remembers what it was given, with its stamps.
 */
final class CapturingMessageBus implements MessageBusInterface
{
    /** @var list<array{object, list<\Symfony\Component\Messenger\Stamp\StampInterface>}> */
    public array $dispatched = [];

    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $this->dispatched[] = [$message, array_values($stamps)];

        return new Envelope($message, $stamps);
    }
}
