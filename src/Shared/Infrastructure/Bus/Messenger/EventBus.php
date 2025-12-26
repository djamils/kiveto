<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus\Messenger;

use App\Shared\Application\Bus\EventBusInterface;
use Symfony\Component\Messenger\MessageBusInterface;

readonly class EventBus implements EventBusInterface
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    public function publish(object ...$events): void
    {
        foreach ($events as $event) {
            $this->bus->dispatch($event);
        }
    }
}
