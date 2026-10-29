<?php

declare(strict_types=1);

namespace App\System\Notification\Infrastructure\Delivery\Simulator;

use App\System\Notification\Domain\Message\Channel;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

/**
 * Schedules the faked delivery webhook of a message the simulator handed
 * over, after %notification.simulator.delivery_delay% milliseconds.
 */
final readonly class SimulatedDeliveryScheduler
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private int $delay,
    ) {
    }

    public function schedule(Channel $channel, string $externalId, string $recipient): void
    {
        $this->commandBus->dispatch(new AdvanceSimulatedDelivery($channel->value, $externalId, $recipient), [new DelayStamp($this->delay)]);
    }
}
