<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Infrastructure\Messaging\Consumer;

use App\System\ElectronicInvoicing\Domain\Event\TransmissionQueued;
use App\System\ElectronicInvoicing\Infrastructure\Messaging\TransmissionStatusPublisher;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'messenger.bus.event')]
final readonly class TransmissionQueuedIntegrationBridge
{
    public function __construct(private TransmissionStatusPublisher $publisher)
    {
    }

    public function __invoke(TransmissionQueued $event): void
    {
        $this->publisher->publish($event->transmissionId, $event->occurredAt);
    }
}
