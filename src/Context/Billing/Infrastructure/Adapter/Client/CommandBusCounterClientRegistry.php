<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Adapter\Client;

use App\Context\Billing\Application\Command\RecordCounterSale\CounterSaleNewClient;
use App\Context\Billing\Application\Port\CounterClientRegistryInterface;
use App\Context\Client\Application\Command\CreateClient\ClientTaxProfileDto;
use App\Context\Client\Application\Command\CreateClient\ContactMethodDto;
use App\Context\Client\Application\Command\CreateClient\CreateClient;
use App\Context\Client\Application\Command\UpdateClientPostalAddress\PostalAddressDto;
use App\Shared\Application\Bus\CommandBusInterface;

/**
 * Asks the Client context to open the record, in a single command: the
 * civility, the phone and the e-mail given at the desk (each the primary
 * one of its kind) and, for a professional, its tax profile.
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
            // A professional may leave its landline; a person gives a mobile.
            $label      = $client->isProfessional() && !$client->hasMobile() ? 'work' : 'mobile';
            $contacts[] = new ContactMethodDto(type: 'phone', label: $label, value: $client->internationalPhone(), isPrimary: true);
        }

        if ('' !== $client->email) {
            $contacts[] = new ContactMethodDto(type: 'email', label: $client->isProfessional() ? 'work' : 'home', value: $client->email, isPrimary: true);
        }

        $clientId = $this->commandBus->dispatch(new CreateClient(
            clinicId: $clinicId,
            firstName: $client->firstName,
            lastName: $client->lastName,
            contactMethods: $contacts,
            civility: $client->civility,
            postalAddress: $client->hasAddress() ? new PostalAddressDto(
                streetLine1: mb_trim($client->street),
                city: mb_trim($client->city),
                countryCode: 'FR',
                postalCode: '' === mb_trim($client->postalCode) ? null : mb_trim($client->postalCode),
            ) : null,
            taxProfile: null === $client->taxProfile ? null : new ClientTaxProfileDto(
                status: $client->taxProfile->status,
                legalName: $client->taxProfile->legalName,
                siren: $client->taxProfile->siren,
                vatNumber: $client->taxProfile->vatNumber,
            ),
        ));
        \assert(\is_string($clientId));

        return $clientId;
    }
}
