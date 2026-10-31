<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Messaging\Consumer;

use App\Context\ClientPortal\Application\Command\BackfillDocumentPets\BackfillDocumentPets;
use App\Context\Patient\Domain\Event\PatientLinkedToAnimalIntegrationEvent;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/** Documents published before the patient was reconciled get its animal. */
#[AsMessageHandler(bus: 'messenger.bus.integration_event')]
final readonly class PatientLinkedToAnimalIntegrationEventConsumer
{
    public function __construct(private CommandBusInterface $commandBus)
    {
    }

    public function __invoke(PatientLinkedToAnimalIntegrationEvent $event): void
    {
        $this->commandBus->dispatch(new BackfillDocumentPets($event->clinicId, $event->patientId, $event->animalId));
    }
}
