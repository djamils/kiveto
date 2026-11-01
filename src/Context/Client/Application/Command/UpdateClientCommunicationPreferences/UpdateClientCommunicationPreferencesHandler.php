<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Command\UpdateClientCommunicationPreferences;

use App\Context\Client\Domain\Exception\StaleConsentsException;
use App\Context\Client\Domain\Repository\ClientRepositoryInterface;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Context\Client\Domain\ValueObject\ConsentActor;
use App\Context\Client\Domain\ValueObject\ConsentActorType;
use App\Context\Client\Domain\ValueObject\ConsentKind;
use App\Context\Client\Domain\ValueObject\ConsentSource;
use App\Context\Client\Domain\ValueObject\ContactChannel;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The client row stays locked until the transaction ends: a Portal change
 * of the same client waits, then the version check sees it.
 */
#[AsMessageHandler]
final readonly class UpdateClientCommunicationPreferencesHandler
{
    public function __construct(
        private ClientRepositoryInterface $clientRepository,
        private DomainEventPublisher $domainEventPublisher,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(UpdateClientCommunicationPreferences $command): void
    {
        $client = $this->clientRepository->getForUpdate(
            ClinicId::fromString($command->clinicId),
            ClientId::fromString($command->clientId),
        );

        if (null !== $command->expectedVersion && $command->expectedVersion !== $client->version()) {
            throw new StaleConsentsException($command->clientId, $command->expectedVersion, $client->version());
        }

        $channelsByKind = [];

        foreach ($command->channelsByConsentKind as $kind => $channels) {
            $channelsByKind[ConsentKind::from($kind)->value] = array_map(
                static fn (string $channel): ContactChannel => ContactChannel::from($channel),
                $channels,
            );
        }

        $client->updateCommunicationPreferences(
            null === $command->preferredChannel ? null : ContactChannel::from($command->preferredChannel),
            $channelsByKind,
            $this->clock->now(),
            ConsentSource::from($command->source),
            new ConsentActor(ConsentActorType::from($command->actorType), $command->actorId, $command->authorLabel),
        );

        $this->clientRepository->save($client);
        $this->domainEventPublisher->publish($client);
    }
}
