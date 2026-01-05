<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus\Messenger;

use App\Shared\Application\Bus\EventBusInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

readonly class EventBus implements EventBusInterface
{
    public function __construct(private MessageBusInterface $messageBus)
    {
    }

    /**
     * @param list<object> $stamps
     */
    public function publish(array $stamps, object ...$events): void
    {
        foreach ($events as $event) {
            /** @var array<\Symfony\Component\Messenger\Stamp\StampInterface> $stampsArray */
            $stampsArray = $stamps;
            $envelope    = Envelope::wrap($event, $stampsArray);
            $this->messageBus->dispatch($envelope);
        }
    }
}
