<?php

declare(strict_types=1);

namespace App\Context\Admission\Infrastructure\Messaging\Consumer;

use App\Context\Admission\Domain\Event\AdmissionClosed;
use App\Context\Admission\Domain\Event\AdmissionClosedIntegrationEvent;
use App\Shared\Application\Event\IntegrationEventPublisher;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Bridges the AdmissionClosed domain event to an integration event
 * so that other BCs (e.g. Regulatory) can react without depending on
 * the Admission BC's domain event bus.
 */
#[AsMessageHandler(bus: 'messenger.bus.event')]
final readonly class AdmissionClosedIntegrationBridge
{
    public function __construct(private IntegrationEventPublisher $integrationEventPublisher)
    {
    }

    public function __invoke(AdmissionClosed $event): void
    {
        $this->integrationEventPublisher->publish(new AdmissionClosedIntegrationEvent(
            admissionId: $event->admissionId,
            patientId: $event->patientId,
            clinicId: $event->clinicId,
            reason: $event->reason,
            closedAt: $event->closedAt,
        ));
    }
}
