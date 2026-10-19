<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Command\UpdateClientTaxProfile;

use App\Context\Client\Application\Command\CreateClient\ClientTaxProfileFactory;
use App\Context\Client\Domain\Repository\ClientRepositoryInterface;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateClientTaxProfileHandler
{
    public function __construct(
        private ClientRepositoryInterface $clientRepository,
        private DomainEventPublisher $domainEventPublisher,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(UpdateClientTaxProfile $command): void
    {
        // The repository only finds the client within this clinic.
        $client = $this->clientRepository->get(
            ClinicId::fromString($command->clinicId),
            ClientId::fromString($command->clientId),
        );

        $client->updateTaxProfile(ClientTaxProfileFactory::fromDto($command->taxProfile), $this->clock->now());

        $this->clientRepository->save($client);
        $this->domainEventPublisher->publish($client);
    }
}
