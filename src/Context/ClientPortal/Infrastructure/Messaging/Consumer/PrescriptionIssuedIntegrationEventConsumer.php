<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Messaging\Consumer;

use App\Context\ClientPortal\Application\Command\PublishPrescription\PublishPrescription;
use App\Context\Prescription\Domain\Event\PrescriptionIssuedIntegrationEvent;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/** Every issued prescription is offered to the Portal. */
#[AsMessageHandler(bus: 'messenger.bus.integration_event')]
final readonly class PrescriptionIssuedIntegrationEventConsumer
{
    public function __construct(private CommandBusInterface $commandBus)
    {
    }

    public function __invoke(PrescriptionIssuedIntegrationEvent $event): void
    {
        $this->commandBus->dispatch(new PublishPrescription($event->clinicId, $event->prescriptionId));
    }
}
