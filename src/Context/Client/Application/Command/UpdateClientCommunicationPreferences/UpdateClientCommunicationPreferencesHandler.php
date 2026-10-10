<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Command\UpdateClientCommunicationPreferences;

use App\Context\Client\Domain\Repository\ClientRepositoryInterface;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Context\Client\Domain\ValueObject\ConsentKind;
use App\Context\Client\Domain\ValueObject\ContactChannel;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

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
        $client = $this->clientRepository->get(
            ClinicId::fromString($command->clinicId),
            ClientId::fromString($command->clientId),
        );

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
        );

        $this->clientRepository->save($client);
        $this->domainEventPublisher->publish($client);
    }
}
