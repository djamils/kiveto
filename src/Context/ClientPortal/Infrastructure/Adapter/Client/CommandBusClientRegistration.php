<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Adapter\Client;

use App\Context\Client\Application\Command\CreateClient\ContactMethodDto;
use App\Context\Client\Application\Command\CreateClient\CreateClient;
use App\Context\ClientPortal\Application\Port\ClientRegistrationInterface;
use App\Shared\Application\Bus\CommandBusInterface;

/** Client's `CreateClient`: an individual file, no animal, no consent given. */
final readonly class CommandBusClientRegistration implements ClientRegistrationInterface
{
    public function __construct(private CommandBusInterface $commandBus)
    {
    }

    public function register(string $clinicId, string $firstName, string $lastName, string $mobilePhone, ?string $actorUserId): string
    {
        $clientId = $this->commandBus->dispatch(new CreateClient(
            clinicId: $clinicId,
            firstName: $firstName,
            lastName: $lastName,
            contactMethods: [new ContactMethodDto(type: 'phone', label: 'mobile', value: $mobilePhone, isPrimary: true)],
            actorType: null === $actorUserId ? 'system' : 'staff',
            actorId: $actorUserId,
        ));
        \assert(\is_string($clientId));

        return $clientId;
    }
}
