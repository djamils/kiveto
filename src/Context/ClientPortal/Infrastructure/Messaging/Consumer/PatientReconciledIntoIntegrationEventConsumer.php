<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Messaging\Consumer;

use App\Context\ClientPortal\Application\Command\RepointDocumentPatient\RepointDocumentPatient;
use App\Context\Patient\Domain\Event\PatientReconciledIntoIntegrationEvent;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/** Documents of a merged patient follow the patient it was merged into. */
#[AsMessageHandler(bus: 'messenger.bus.integration_event')]
final readonly class PatientReconciledIntoIntegrationEventConsumer
{
    public function __construct(private CommandBusInterface $commandBus)
    {
    }

    public function __invoke(PatientReconciledIntoIntegrationEvent $event): void
    {
        $this->commandBus->dispatch(new RepointDocumentPatient($event->clinicId, $event->sourcePatientId, $event->targetPatientId));
    }
}
