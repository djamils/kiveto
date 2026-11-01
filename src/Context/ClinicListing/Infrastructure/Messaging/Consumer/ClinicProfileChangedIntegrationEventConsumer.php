<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Infrastructure\Messaging\Consumer;

use App\Context\Clinic\Domain\Event\ClinicProfileChangedIntegrationEvent;
use App\Context\ClinicListing\Application\Command\RefreshClinicListing\RefreshClinicListing;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/** Something the clinic's public profile shows changed: its listing is re-read. */
#[AsMessageHandler(bus: 'messenger.bus.integration_event')]
final readonly class ClinicProfileChangedIntegrationEventConsumer
{
    public function __construct(private CommandBusInterface $commandBus)
    {
    }

    public function __invoke(ClinicProfileChangedIntegrationEvent $event): void
    {
        $this->commandBus->dispatch(new RefreshClinicListing($event->clinicId));
    }
}
