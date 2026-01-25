<?php

declare(strict_types=1);

namespace App\Client\Application\Command\ArchiveClient;

use App\Client\Domain\Event\ClientArchivedIntegrationEvent;
use App\Client\Domain\Exception\ClientClinicMismatchException;
use App\Client\Domain\Repository\ClientRepositoryInterface;
use App\Client\Domain\ValueObject\ClientId;
use App\Clinic\Domain\ValueObject\ClinicId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Application\Event\IntegrationEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ArchiveClientHandler
{
    public function __construct(
        private ClientRepositoryInterface $clientRepository,
        private DomainEventPublisher $domainEventPublisher,
        private IntegrationEventPublisher $integrationEventPublisher,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(ArchiveClient $command): void
    {
        $clinicId = ClinicId::fromString($command->clinicId);
        $clientId = ClientId::fromString($command->clientId);

        $client = $this->clientRepository->get($clinicId, $clientId);

        if (!$client->clinicId()->equals($clinicId)) {
            throw ClientClinicMismatchException::create(
                $clientId->toString(),
                $clinicId->toString(),
            );
        }

        $client->archive($this->clock->now());

        $this->clientRepository->save($client);
        $this->domainEventPublisher->publish($client);

        $this->integrationEventPublisher->publish(
            new ClientArchivedIntegrationEvent(
                clientId: $clientId->toString(),
                clinicId: $clinicId->toString(),
            ),
        );
    }
}
