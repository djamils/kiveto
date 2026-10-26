<?php

declare(strict_types=1);

namespace App\System\Notification\Infrastructure\Delivery\Simulator;

use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\Notification\Application\Command\RecordDeliveryEvent\RecordDeliveryEvent;
use App\System\Notification\Domain\Message\Channel;
use App\System\Notification\Domain\Message\DeliveryStatus;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Plays the provider: an address containing `bounce@` bounces, an SMS
 * number ending in 0000 is rejected, anything else is delivered — recorded
 * through the same command as a real webhook.
 */
#[AsMessageHandler(bus: 'messenger.bus.command')]
final readonly class AdvanceSimulatedDeliveryHandler
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(AdvanceSimulatedDelivery $command): void
    {
        [$status, $reason] = match (true) {
            str_contains($command->recipient, 'bounce@')                                            => [DeliveryStatus::BOUNCED, 'Adresse inexistante (simulation)'],
            Channel::SMS->value === $command->channel && str_ends_with($command->recipient, '0000') => [DeliveryStatus::REJECTED, 'Numéro refusé (simulation)'],
            default                                                                                 => [DeliveryStatus::DELIVERED, null],
        };

        $this->commandBus->dispatch(new RecordDeliveryEvent(
            channel: $command->channel,
            externalId: $command->externalId,
            kind: $status->value,
            occurredAt: $this->clock->now()->format(\DateTimeInterface::ATOM),
            reason: $reason,
        ));
    }
}
