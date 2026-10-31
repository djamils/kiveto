<?php

declare(strict_types=1);

namespace App\Context\Prescription\Infrastructure\Messaging\Consumer;

use App\Context\Prescription\Domain\Event\PrescriptionCancelled;
use App\Context\Prescription\Domain\Event\PrescriptionCancelledIntegrationEvent;
use App\Shared\Application\Event\IntegrationEventPublisher;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * A cancelled prescription concerns the Client Portal: it leaves through the
 * outbox, in the transaction that cancelled it.
 */
#[AsMessageHandler(bus: 'messenger.bus.event')]
final readonly class PrescriptionCancelledIntegrationBridge
{
    public function __construct(private IntegrationEventPublisher $integrationEventPublisher)
    {
    }

    public function __invoke(PrescriptionCancelled $event): void
    {
        $this->integrationEventPublisher->publish(new PrescriptionCancelledIntegrationEvent(
            prescriptionId: $event->prescriptionId,
            clinicId: $event->clinicId,
        ));
    }
}
