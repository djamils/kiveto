<?php

declare(strict_types=1);

namespace App\Client\Application\Command\CreateClient;

use App\Client\Application\Port\ClientRepositoryInterface;
use App\Client\Domain\Client;
use App\Client\Domain\ValueObject\ClientIdentity;
use App\Client\Domain\ValueObject\ContactLabel;
use App\Client\Domain\ValueObject\ContactMethod;
use App\Client\Domain\ValueObject\ContactMethodType;
use App\Clinic\Domain\ValueObject\ClinicId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\PhoneNumber;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateClientHandler
{
    public function __construct(
        private ClientRepositoryInterface $clientRepository,
        private DomainEventPublisher $domainEventPublisher,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CreateClient $command): string
    {
        $clinicId = ClinicId::fromString($command->clinicId);
        $clientId = $this->clientRepository->nextId();

        $identity = new ClientIdentity(
            firstName: $command->firstName,
            lastName: $command->lastName,
        );

        $contactMethods = $this->buildContactMethods($command->contactMethods);

        $client = Client::create(
            id: $clientId,
            clinicId: $clinicId,
            identity: $identity,
            contactMethods: $contactMethods,
            createdAt: $this->clock->now(),
        );

        $this->clientRepository->save($client);
        $this->domainEventPublisher->publish($client);

        return $clientId->toString();
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
