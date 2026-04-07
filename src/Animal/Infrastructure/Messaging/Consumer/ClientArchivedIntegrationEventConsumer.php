<?php

declare(strict_types=1);

namespace App\Animal\Infrastructure\Messaging\Consumer;

use App\Animal\Domain\Repository\AnimalRepositoryInterface;
use App\Client\Domain\Event\ClientArchivedIntegrationEvent;
use App\Clinic\Domain\ValueObject\ClinicId;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'messenger.bus.integration_event')]
final readonly class ClientArchivedIntegrationEventConsumer
{
    public function __construct(
        private AnimalRepositoryInterface $animalRepository,
        private EventBusInterface $eventBus,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ClientArchivedIntegrationEvent $event): void
    {
        $payload  = $event->payload();
        $clientId = $payload['clientId'];
        \assert(\is_string($clientId));

        $clinicIdRaw = $payload['clinicId'];
        \assert(\is_string($clinicIdRaw));
        $clinicId = ClinicId::fromString($clinicIdRaw);

        $now = $this->clock->now();

        $this->logger->info('Processing ClientArchivedIntegrationEvent', [
            'clientId' => $clientId,
            'clinicId' => $clinicId->toString(),
        ]);

        // Find all animals owned by this client in this clinic
        $animals = $this->animalRepository->findByActiveOwner($clinicId, $clientId);

        if ([] === $animals) {
            $this->logger->info('No animals found for archived client', [
                'clientId' => $clientId,
                'clinicId' => $clinicId->toString(),
            ]);

            return;
        }

        $this->logger->info('Found animals for archived client', [
            'clientId'    => $clientId,
            'clinicId'    => $clinicId->toString(),
            'animalCount' => \count($animals),
        ]);

        // Resolve ownerships for each animal
        foreach ($animals as $animal) {
            try {
                $animal->resolveOwnershipForArchivedClient($clientId, $now);
                $this->animalRepository->save($animal);
                $this->eventBus->publish([], ...$animal->pullDomainEvents());

                $this->logger->info('Resolved ownership for animal', [
                    'animalId'     => $animal->id()->value(),
                    'clientId'     => $clientId,
                    'animalStatus' => $animal->status()->value,
                ]);
            } catch (\Throwable $exception) {
                $this->logger->error('Failed to resolve ownership for animal', [
                    'animalId' => $animal->id()->value(),
                    'clientId' => $clientId,
                    'error'    => $exception->getMessage(),
                ]);

                throw $exception;
            }
        }
    }
}
