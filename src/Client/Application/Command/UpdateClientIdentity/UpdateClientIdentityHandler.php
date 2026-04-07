<?php

declare(strict_types=1);

namespace App\Client\Application\Command\UpdateClientIdentity;

use App\Client\Domain\Exception\ClientClinicMismatchException;
use App\Client\Domain\Repository\ClientRepositoryInterface;
use App\Client\Domain\ValueObject\ClientId;
use App\Client\Domain\ValueObject\ClientIdentity;
use App\Clinic\Domain\ValueObject\ClinicId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateClientIdentityHandler
{
    public function __construct(
        private ClientRepositoryInterface $clientRepository,
        private DomainEventPublisher $domainEventPublisher,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(UpdateClientIdentity $command): void
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

        $newIdentity = new ClientIdentity(
            firstName: $command->firstName,
            lastName: $command->lastName,
        );

        $client->updateIdentity($newIdentity, $this->clock->now());

        $this->clientRepository->save($client);
        $this->domainEventPublisher->publish($client);
    }
}
