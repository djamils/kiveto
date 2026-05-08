<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Command\UnarchiveClient;

use App\Context\Client\Domain\Exception\ClientClinicMismatchException;
use App\Context\Client\Domain\Repository\ClientRepositoryInterface;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UnarchiveClientHandler
{
    public function __construct(
        private ClientRepositoryInterface $clientRepository,
        private DomainEventPublisher $domainEventPublisher,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(UnarchiveClient $command): void
    {
        $clinicId = ClinicId::fromString($command->clinicId);
        $clientId = ClientId::fromString($command->clientId);

        $client = $this->clientRepository->get($clinicId, $clientId);

        if (!$client->clinicId()->equals($clinicId)) {
            throw new ClientClinicMismatchException(
                $clientId->toString(),
                $clinicId->toString(),
            );
        }

        $client->unarchive($this->clock->now());

        $this->clientRepository->save($client);
        $this->domainEventPublisher->publish($client);
    }
}
