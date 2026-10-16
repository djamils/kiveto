<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Adapter\Client;

use App\Context\Billing\Application\Command\RecordCounterSale\CounterSaleNewClient;
use App\Context\Billing\Application\Port\CounterClientRegistryInterface;
use App\Context\Client\Application\Command\CreateClient\ContactMethodDto;
use App\Context\Client\Application\Command\CreateClient\CreateClient;
use App\Context\Client\Application\Command\UpdateClientIdentity\UpdateClientIdentity;
use App\Shared\Application\Bus\CommandBusInterface;

/**
 * Asks the Client context to open the record: the mobile and the e-mail
 * given at the desk (each the primary one of its kind), then the
 * civility.
 */
final readonly class CommandBusCounterClientRegistry implements CounterClientRegistryInterface
{
    public function __construct(private CommandBusInterface $commandBus)
    {
    }

    public function register(string $clinicId, CounterSaleNewClient $client): string
    {
        $contacts = [];

        if ('' !== $client->phone) {
            $contacts[] = new ContactMethodDto(type: 'phone', label: 'mobile', value: $client->internationalPhone(), isPrimary: true);
        }

        if ('' !== $client->email) {
            $contacts[] = new ContactMethodDto(type: 'email', label: 'home', value: $client->email, isPrimary: true);
        }

        $clientId = $this->commandBus->dispatch(new CreateClient(
            clinicId: $clinicId,
            firstName: $client->firstName,
            lastName: $client->lastName,
            contactMethods: $contacts,
        ));
        \assert(\is_string($clientId));

        $this->commandBus->dispatch(new UpdateClientIdentity($clinicId, $clientId, $client->firstName, $client->lastName, $client->civility));

        return $clientId;
    }
}
