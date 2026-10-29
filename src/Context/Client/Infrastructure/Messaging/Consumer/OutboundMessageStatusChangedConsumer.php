<?php

declare(strict_types=1);

namespace App\Context\Client\Infrastructure\Messaging\Consumer;

use App\Context\Client\Application\Command\RecordAutomatedCommunication\RecordAutomatedCommunication;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Notification\Domain\Event\OutboundMessageStatusChangedIntegrationEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Every status a message to a client takes reaches the client's journal
 * (D7). Messages without a client (staff accounts, tests) are not
 * journaled.
 */
#[AsMessageHandler(bus: 'messenger.bus.integration_event')]
final readonly class OutboundMessageStatusChangedConsumer
{
    public function __construct(
        private CommandBusInterface $commandBus,
    ) {
    }

    public function __invoke(OutboundMessageStatusChangedIntegrationEvent $event): void
    {
        if (null === $event->clientId || null === $event->clinicId) {
            return;
        }

        $this->commandBus->dispatch(new RecordAutomatedCommunication(
            notificationMessageId: $event->id,
            clinicId: $event->clinicId,
            clientId: $event->clientId,
            channel: $event->channel,
            subject: $event->subjectLine,
            recipientLabel: $event->recipientLabel,
            status: $event->status,
            occurredAt: $event->occurredAt,
            sequence: $event->sequence,
        ));
    }
}
