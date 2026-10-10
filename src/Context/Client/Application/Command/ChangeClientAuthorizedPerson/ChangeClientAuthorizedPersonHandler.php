<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Command\ChangeClientAuthorizedPerson;

use App\Context\Client\Domain\Repository\ClientRepositoryInterface;
use App\Context\Client\Domain\ValueObject\AuthorizedPerson;
use App\Context\Client\Domain\ValueObject\AuthorizedPersonRelationship;
use App\Context\Client\Domain\ValueObject\Civility;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\PhoneNumber;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ChangeClientAuthorizedPersonHandler
{
    public function __construct(
        private ClientRepositoryInterface $clientRepository,
        private DomainEventPublisher $domainEventPublisher,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(ChangeClientAuthorizedPerson $command): void
    {
        $client = $this->clientRepository->get(
            ClinicId::fromString($command->clinicId),
            ClientId::fromString($command->clientId),
        );

        $now    = $this->clock->now();
        $person = null;

        if (null !== $command->authorizedPerson) {
            $dto = $command->authorizedPerson;

            $person = new AuthorizedPerson(
                civility: Civility::from($dto->civility),
                firstName: $dto->firstName,
                lastName: $dto->lastName,
                relationship: AuthorizedPersonRelationship::from($dto->relationship),
                phoneNumber: null === $dto->phoneNumber ? null : PhoneNumber::fromString($dto->phoneNumber),
                authorizedAt: $now,
            );
        }

        $client->changeAuthorizedPerson($person, $now);

        $this->clientRepository->save($client);
        $this->domainEventPublisher->publish($client);
    }
}
