<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Adapter\Client;

use App\Context\Client\Application\Query\GetClientNotificationContact\ClientNotificationContact;
use App\Context\Client\Application\Query\GetClientNotificationContact\GetClientNotificationContact;
use App\Context\Scheduling\Application\Port\ClientContact;
use App\Context\Scheduling\Application\Port\ClientContactInterface;
use App\Shared\Application\Bus\QueryBusInterface;

/**
 * Client's GetClientNotificationContact, with its consent rules applied for
 * appointments: the transactional channel, and the reminder channels of the
 * « Rappels de rendez-vous » consent. The greeting spells the civility out:
 * « Madame Dubois ».
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

        if (!$contact instanceof ClientNotificationContact) {
            return null;
        }

        $transactional = $contact->transactionalChannel();

        return new ClientContact(
            clientId: $contact->clientId,
            name: $contact->displayName,
            addressee: self::addressee($contact),
            email: $contact->email,
            mobile: $contact->mobile,
            transactionalChannel: null !== $transactional ? self::CHANNELS[$transactional] ?? null : null,
            reminderChannels: array_values(array_filter(array_map(
                static fn (string $channel): ?string => self::CHANNELS[$channel] ?? null,
                $contact->channelsFor(ClientNotificationContact::APPOINTMENT_REMINDERS),
            ))),
            archived: $contact->archived,
        );
    }

    private static function addressee(ClientNotificationContact $contact): string
    {
        $civility = self::CIVILITIES[$contact->civility] ?? null;

        return null !== $civility && '' !== $contact->lastName
            ? $civility . ' ' . $contact->lastName
            : $contact->displayName;
    }
}
