<?php

declare(strict_types=1);

namespace App\Client\Application\Command\ReplaceClientContactMethods;

use App\Client\Domain\Exception\ClientClinicMismatchException;
use App\Client\Domain\Repository\ClientRepositoryInterface;
use App\Client\Domain\ValueObject\ClientId;
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
final readonly class ReplaceClientContactMethodsHandler
{
    public function __construct(
        private ClientRepositoryInterface $clientRepository,
        private DomainEventPublisher $domainEventPublisher,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(ReplaceClientContactMethods $command): void
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

        $contactMethods = $this->buildContactMethods($command->contactMethods);

        $client->replaceContactMethods($contactMethods, $this->clock->now());

        $this->clientRepository->save($client);
        $this->domainEventPublisher->publish($client);
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
