<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Command\CreateClient;

use App\Context\Client\Application\Port\ClientAnimalRegistryInterface;
use App\Context\Client\Domain\Client;
use App\Context\Client\Domain\Repository\ClientRepositoryInterface;
use App\Context\Client\Domain\ValueObject\Civility;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClientIdentity;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Context\Client\Domain\ValueObject\ConsentKind;
use App\Context\Client\Domain\ValueObject\ContactChannel;
use App\Context\Client\Domain\ValueObject\ContactLabel;
use App\Context\Client\Domain\ValueObject\ContactMethod;
use App\Context\Client\Domain\ValueObject\ContactMethodType;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\PhoneNumber;
use App\Shared\Domain\ValueObject\PostalAddress;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class CreateClientHandler
{
    public function __construct(
        private ClientRepositoryInterface $clientRepository,
        private DomainEventPublisher $domainEventPublisher,
        private ClockInterface $clock,
        private ClientAnimalRegistryInterface $animalRegistry,
    ) {
    }

    public function __invoke(CreateClient $command): string
    {
        $clinicId = ClinicId::fromString($command->clinicId);
        $clientId = ClientId::fromString(Uuid::v7()->toString());

        $identity = new ClientIdentity(
            firstName: $command->firstName,
            lastName: $command->lastName,
            civility: Civility::from($command->civility),
        );

        $contactMethods = $this->buildContactMethods($command->contactMethods);
        $now            = $this->clock->now();

        $client = Client::create(
            id: $clientId,
            clinicId: $clinicId,
            identity: $identity,
            contactMethods: $contactMethods,
            createdAt: $now,
            taxProfile: null === $command->taxProfile ? null : ClientTaxProfileFactory::fromDto($command->taxProfile),
        );

        if (null !== $command->postalAddress) {
            $dto = $command->postalAddress;
            $client->updatePostalAddress(PostalAddress::create(
                streetLine1: $dto->streetLine1,
                city: $dto->city,
                countryCode: $dto->countryCode,
                streetLine2: $dto->streetLine2,
                postalCode: $dto->postalCode,
                region: $dto->region,
            ), $now);
        }

        if (null !== $command->preferredChannel || [] !== $command->channelsByConsentKind) {
            $client->updateCommunicationPreferences(
                null === $command->preferredChannel ? null : ContactChannel::from($command->preferredChannel),
                $this->channelsByKind($command->channelsByConsentKind),
                $now,
            );
        }

        $this->clientRepository->save($client);
        $this->domainEventPublisher->publish($client);

        // The animals are recorded once their owner exists, in the same
        // transaction: a refused animal leaves no half-opened record behind.
        foreach ($command->animals as $animal) {
            $this->animalRegistry->register($command->clinicId, $clientId->toString(), $animal);
        }

        return $clientId->toString();
    }

    /**
     * @param array<string, list<string>> $raw
     *
     * @return array<string, list<ContactChannel>>
     */
    private function channelsByKind(array $raw): array
    {
        $channelsByKind = [];

        foreach ($raw as $kind => $channels) {
            $channelsByKind[ConsentKind::from($kind)->value] = array_map(
                static fn (string $channel): ContactChannel => ContactChannel::from($channel),
                $channels,
            );
        }

        return $channelsByKind;
    }

    /**
     * @param list<ContactMethodDto> $dtos
     *
     * @return list<ContactMethod>
     */
    private function buildContactMethods(array $dtos): array
    {
        return array_map(
            function (ContactMethodDto $dto): ContactMethod {
                $type  = ContactMethodType::from($dto->type);
                $label = ContactLabel::from($dto->label);

                if (ContactMethodType::PHONE === $type) {
                    return ContactMethod::phone(
                        PhoneNumber::fromString($dto->value),
                        $label,
                        $dto->isPrimary,
                    );
                }

                return ContactMethod::email(
                    EmailAddress::fromString($dto->value),
                    $label,
                    $dto->isPrimary,
                );
            },
            $dtos,
        );
    }
}
