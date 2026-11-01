<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Command\SetClientConsent;

use App\Context\Client\Domain\Exception\InvalidConsentChannelException;
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
 * The client row stays locked until the transaction ends, so two changes of
 * the same client never interleave.
 */
#[AsMessageHandler]
final readonly class SetClientConsentHandler
{
    public function __construct(
        private ClientRepositoryInterface $clientRepository,
        private DomainEventPublisher $domainEventPublisher,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SetClientConsent $command): void
    {
        $kind = ConsentKind::tryFrom($command->kind)
            ?? throw new \InvalidArgumentException(\sprintf('Unknown consent kind "%s".', $command->kind));

        $channels = array_map(
            static function (string $value): ContactChannel {
                $channel = ContactChannel::tryFrom($value);

                if (null === $channel || !$channel->isAutomated()) {
                    throw new InvalidConsentChannelException($value);
                }

                return $channel;
            },
            $command->channels,
        );

        $actor = new ConsentActor(ConsentActorType::from($command->actorType), $command->actorId, $command->authorLabel);

        $client = $this->clientRepository->getForUpdate(
            ClinicId::fromString($command->clinicId),
            ClientId::fromString($command->clientId),
        );

        $client->setConsent(
            $kind,
            $channels,
            $this->clock->now(),
            ConsentSource::from($command->source),
            $actor,
            $command->wordingVersion,
        );

        $this->clientRepository->save($client);
        $this->domainEventPublisher->publish($client);
    }
}
