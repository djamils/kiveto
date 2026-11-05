<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Adapter\Client;

use App\Context\Client\Application\Query\GetClientById\ClientView;
use App\Context\Client\Application\Query\GetClientById\GetClientById;
use App\Context\Client\Application\Query\GetClientNotificationContact\ClientNotificationContact;
use App\Context\Client\Application\Query\GetClientNotificationContact\GetClientNotificationContact;
use App\Context\Client\Application\Query\GetClientsNotificationContacts\GetClientsNotificationContacts;
use App\Context\Client\Application\Query\ListClientsByMobilePhone\ListClientsByMobilePhone;
use App\Context\Client\Application\Query\ListClientsByMobilePhone\MobileClientMatch;
use App\Context\ClientPortal\Application\Port\ClientDirectoryInterface;
use App\Context\ClientPortal\Application\Port\MobileFileMatch;
use App\Context\ClientPortal\Application\Port\PortalClientContact;
use App\Context\ClientPortal\Application\Port\PortalClientSnapshot;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\ValueObject\PostalAddress;

/**
 * Client's GetClientNotificationContact (the SMS-capable phone, primary
 * first, and the e-mail) and GetClientById (identity and postal address).
 * Consents are not applied: an invitation is an explicit staff action; the
 * transactional channel (preferred, else e-mail, else SMS) carries the
 * answer to a Portal request. ListClientsByMobilePhone (cross-clinic) feeds
 * the link suggestions.
 */
final readonly class QueryBusClientDirectory implements ClientDirectoryInterface
{
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function contactOf(string $clinicId, string $clientId): ?PortalClientContact
    {
        $contact = $this->queryBus->ask(new GetClientNotificationContact($clinicId, $clientId));

        return $contact instanceof ClientNotificationContact ? self::portal($contact) : null;
    }

    public function contactsOf(string $clinicId, array $clientIds): array
    {
        if ([] === $clientIds) {
            return [];
        }

        $contacts = $this->queryBus->ask(new GetClientsNotificationContacts($clinicId, $clientIds));
        $portal   = [];

        foreach (\is_array($contacts) ? $contacts : [] as $contact) {
            if ($contact instanceof ClientNotificationContact) {
                $portal[$contact->clientId] = self::portal($contact);
            }
        }

        return $portal;
    }

    public function snapshotOf(string $clinicId, string $clientId): ?PortalClientSnapshot
    {
        $client = $this->queryBus->ask(new GetClientById($clinicId, $clientId));

        if (!$client instanceof ClientView) {
            return null;
        }

        $address = $client->postalAddress;

        return new PortalClientSnapshot(
            firstName: $client->firstName,
            lastName: $client->lastName,
            address: null === $address ? null : PostalAddress::create(
                streetLine1: $address->streetLine1,
                city: $address->city,
                countryCode: $address->countryCode,
                streetLine2: $address->streetLine2,
                postalCode: $address->postalCode,
            ),
        );
    }

    public function mobileMatches(string $phone): array
    {
        $matches = $this->queryBus->ask(new ListClientsByMobilePhone($phone));
        \assert(\is_array($matches));

        return array_values(array_map(static function (mixed $match): MobileFileMatch {
            \assert($match instanceof MobileClientMatch);

            return new MobileFileMatch($match->clinicId, $match->clientId, $match->firstName, $match->lastName, $match->animalNames);
        }, $matches));
    }

    private static function portal(ClientNotificationContact $contact): PortalClientContact
    {
        return new PortalClientContact(
            clientId: $contact->clientId,
            displayName: $contact->displayName,
            firstName: $contact->firstName,
            email: $contact->email,
            mobile: $contact->mobile,
            archived: $contact->archived,
            transactionalChannel: $contact->transactionalChannel(),
        );
    }
}
