<?php

declare(strict_types=1);

namespace App\Client\Application\Command\UpdateClientPostalAddress;

use App\Client\Application\Port\ClientRepositoryInterface;
use App\Client\Domain\Exception\ClientClinicMismatchException;
use App\Client\Domain\ValueObject\ClientId;
use App\Clinic\Domain\ValueObject\ClinicId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\PostalAddress;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateClientPostalAddressHandler
{
    public function __construct(
        private ClientRepositoryInterface $clientRepository,
        private DomainEventPublisher $domainEventPublisher,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(UpdateClientPostalAddress $command): void
    {
        $clinicId = ClinicId::fromString($command->clinicId);
        $clientId = ClientId::fromString($command->clientId);

        $client = $this->clientRepository->get($clinicId, $clientId);

        if (!$client->clinicId()->equals($clinicId)) {
            throw ClientClinicMismatchException::create(
                $clientId->toString(),
                $clinicId->toString(),
            );
        }

        $postalAddress = null;

        if (null !== $command->postalAddress) {
            $dto = $command->postalAddress;

            $postalAddress = PostalAddress::create(
                streetLine1: $dto->streetLine1,
                city: $dto->city,
                countryCode: $dto->countryCode,
                streetLine2: $dto->streetLine2,
                postalCode: $dto->postalCode,
                region: $dto->region,
            );
        }

        $client->updatePostalAddress($postalAddress, $this->clock->now());

        $this->clientRepository->save($client);
        $this->domainEventPublisher->publish($client);
    }
}
