<?php

declare(strict_types=1);

namespace App\Context\Consultation\Infrastructure\Adapter\Client;

use App\Context\Client\Application\Query\GetClientNotificationContact\ClientNotificationContact;
use App\Context\Client\Application\Query\GetClientNotificationContact\GetClientNotificationContact;
use App\Context\Consultation\Application\Port\ClientContact;
use App\Context\Consultation\Application\Port\ClientContactInterface;
use App\Shared\Application\Bus\QueryBusInterface;

/**
 * Client's notification contact query; the greeting spells the civility
 * out: « Madame Dubois ». The documents e-mail is an explicit staff action:
 * it goes to the e-mail on file whatever the consents.
 */
final readonly class QueryBusClientContact implements ClientContactInterface
{
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

        $civility = self::CIVILITIES[$contact->civility] ?? null;

        return new ClientContact(
            clientId: $contact->clientId,
            name: $contact->displayName,
            addressee: null !== $civility && '' !== $contact->lastName ? $civility . ' ' . $contact->lastName : $contact->displayName,
            email: $contact->email,
            archived: $contact->archived,
        );
    }
}
