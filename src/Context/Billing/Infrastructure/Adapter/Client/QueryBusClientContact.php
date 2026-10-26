<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Adapter\Client;

use App\Context\Billing\Application\Port\ClientContact;
use App\Context\Billing\Application\Port\ClientContactInterface;
use App\Context\Client\Application\Query\GetClientNotificationContact\ClientNotificationContact;
use App\Context\Client\Application\Query\GetClientNotificationContact\GetClientNotificationContact;
use App\Context\Client\Application\Query\GetClientsNotificationContacts\GetClientsNotificationContacts;
use App\Shared\Application\Bus\QueryBusInterface;

/**
 * Client's notification contact queries (one client, or one page of payers
 * in a single query), with the « Facturation » consent applied. The
 * greeting spells the civility out: « Madame Dubois ».
 */
final readonly class QueryBusClientContact implements ClientContactInterface
{
    private const array CHANNELS = [
        ClientNotificationContact::CHANNEL_EMAIL => ClientContact::EMAIL,
        ClientNotificationContact::CHANNEL_SMS   => ClientContact::SMS,
    ];

    private const array CIVILITIES = ['Mme' => 'Madame', 'M.' => 'Monsieur'];

    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function contactOf(string $clinicId, string $clientId): ?ClientContact
    {
        $contact = $this->queryBus->ask(new GetClientNotificationContact($clinicId, $clientId));

        return $contact instanceof ClientNotificationContact ? self::contact($contact) : null;
    }

    public function contactsOf(string $clinicId, array $clientIds): array
    {
        $contacts = $this->queryBus->ask(new GetClientsNotificationContacts($clinicId, $clientIds));
        $mapped   = [];

        foreach (\is_array($contacts) ? $contacts : [] as $contact) {
            if ($contact instanceof ClientNotificationContact) {
                $mapped[$contact->clientId] = self::contact($contact);
            }
        }

        return $mapped;
    }

    private static function contact(ClientNotificationContact $contact): ClientContact
    {
        $transactional = $contact->transactionalChannel();
        $civility      = self::CIVILITIES[$contact->civility] ?? null;

        return new ClientContact(
            clientId: $contact->clientId,
            name: $contact->displayName,
            addressee: null !== $civility && '' !== $contact->lastName ? $civility . ' ' . $contact->lastName : $contact->displayName,
            email: $contact->email,
            mobile: $contact->mobile,
            billingChannels: array_values(array_filter(array_map(
                static fn (string $channel): ?string => self::CHANNELS[$channel] ?? null,
                $contact->channelsFor(ClientNotificationContact::BILLING),
            ))),
            transactionalChannel: null !== $transactional ? self::CHANNELS[$transactional] ?? null : null,
            archived: $contact->archived,
        );
    }
}
