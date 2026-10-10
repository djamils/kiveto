<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Port;

use App\Context\Client\Application\Query\ListClientCommunications\ClientCommunicationView;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClinicId;

interface ClientCommunicationReadRepositoryInterface
{
    /**
     * The communications of one client, newest first.
     *
     * @return list<ClientCommunicationView>
     */
    public function listForClient(ClinicId $clinicId, ClientId $clientId): array;
}
