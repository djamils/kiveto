<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Port;

use App\Context\Client\Application\Query\GetClientNotificationContact\ClientNotificationContact;
use App\Context\Client\Domain\ValueObject\ClinicId;

interface ClientNotificationContactReaderInterface
{
    /**
     * The contacts of these clients of the clinic, archived ones included,
     * read in a single query. Unknown ids and clients of another clinic are
     * absent from the result.
     *
     * @param list<string> $clientIds non-empty, UUID strings
     *
     * @return array<string, ClientNotificationContact> keyed by client id
     */
    public function findForClients(ClinicId $clinicId, array $clientIds): array;
}
