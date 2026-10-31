<?php

declare(strict_types=1);

namespace App\Context\Prescription\Infrastructure\Messaging\Consumer;

use App\Context\Prescription\Domain\Event\PrescriptionIssued;
use App\Context\Prescription\Domain\Event\PrescriptionIssuedIntegrationEvent;
use App\Shared\Application\Event\IntegrationEventPublisher;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * A issued prescription concerns the Client Portal: it leaves through the
 * outbox, in the transaction that signed it.
 */
#[AsMessageHandler(bus: 'messenger.bus.event')]
final readonly class PrescriptionIssuedIntegrationBridge
{
    public function __construct(private IntegrationEventPublisher $integrationEventPublisher)
    {
    }

    public function __invoke(PrescriptionIssued $event): void
    {
        $this->integrationEventPublisher->publish(new PrescriptionIssuedIntegrationEvent(
            prescriptionId: $event->prescriptionId,
            clinicId: $event->clinicId,
        ));
    }
}
