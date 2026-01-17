<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus\Messenger;

use App\Shared\Application\Bus\IntegrationEventBusInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\StampInterface;

readonly class IntegrationEventBus implements IntegrationEventBusInterface
{
    public function __construct(private MessageBusInterface $messageBus)
    {
    }

    /**
     * @param array<StampInterface> $stamps
     *
     * @throws ExceptionInterface
     */
    public function publish(array $stamps, object ...$events): void
    {
        foreach ($events as $event) {
            $envelope = Envelope::wrap($event, $stamps);
            $this->messageBus->dispatch($envelope);
        }
    }
}
