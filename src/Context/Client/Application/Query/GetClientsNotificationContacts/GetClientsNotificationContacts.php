<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\GetClientsNotificationContacts;

use App\Shared\Application\Bus\QueryInterface;

/**
 * GetClientNotificationContact for a whole list screen at once (one query):
 * clientId => ClientNotificationContact, clients of other clinics left out.
 */
final readonly class GetClientsNotificationContacts implements QueryInterface
{
    /**
     * @param list<string> $clientIds UUID strings
     */
    public function __construct(
        public string $clinicId,
        public array $clientIds,
    ) {
    }
}
