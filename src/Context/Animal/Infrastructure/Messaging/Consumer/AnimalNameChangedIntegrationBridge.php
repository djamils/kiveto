<?php

declare(strict_types=1);

namespace App\Context\Animal\Infrastructure\Messaging\Consumer;

use App\Context\Animal\Domain\Event\AnimalNameChanged;
use App\Context\Animal\Domain\Event\AnimalNameChangedIntegrationEvent;
use App\Shared\Application\Event\IntegrationEventPublisher;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'messenger.bus.event')]
final readonly class AnimalNameChangedIntegrationBridge
{
    public function __construct(private IntegrationEventPublisher $integrationEventPublisher)
    {
    }

    public function __invoke(AnimalNameChanged $event): void
    {
        $this->integrationEventPublisher->publish(
            new AnimalNameChangedIntegrationEvent(
                animalId: $event->animalId,
                clinicId: $event->clinicId,
                newName: $event->newName,
            )
        );
    }
}
