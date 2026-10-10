<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Command\LogClientCommunication;

use App\Context\Client\Domain\ClientCommunication;
use App\Context\Client\Domain\Repository\ClientCommunicationRepositoryInterface;
use App\Context\Client\Domain\Repository\ClientRepositoryInterface;
use App\Context\Client\Domain\ValueObject\ClientCommunicationId;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Context\Client\Domain\ValueObject\CommunicationDirection;
use App\Context\Client\Domain\ValueObject\ContactChannel;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class LogClientCommunicationHandler
{
    public function __construct(
        private ClientRepositoryInterface $clientRepository,
        private ClientCommunicationRepositoryInterface $communicationRepository,
        private UuidGeneratorInterface $uuidGenerator,
        private DomainEventPublisher $domainEventPublisher,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(LogClientCommunication $command): string
    {
        $clinicId = ClinicId::fromString($command->clinicId);
        $clientId = ClientId::fromString($command->clientId);

        // Throws when the client does not belong to the clinic.
        $this->clientRepository->get($clinicId, $clientId);

        $communicationId = ClientCommunicationId::fromString($this->uuidGenerator->generate());

        $communication = ClientCommunication::log(
            id: $communicationId,
            clinicId: $clinicId,
            clientId: $clientId,
            channel: ContactChannel::from($command->channel),
            direction: CommunicationDirection::from($command->direction),
            subject: $command->subject,
            body: $command->body,
            authorName: $command->authorName,
            occurredAt: $this->clock->now(),
        );

        $this->communicationRepository->save($communication);
        $this->domainEventPublisher->publish($communication);

        return $communicationId->toString();
    }
}
