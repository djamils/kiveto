<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Port;

use App\Context\Client\Application\Query\ListClientNotes\ClientNoteView;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClinicId;

interface ClientNoteReadRepositoryInterface
{
    /**
     * The notes of one client, newest first.
     *
     * @return list<ClientNoteView>
     */
    public function listForClient(ClinicId $clinicId, ClientId $clientId): array;
}
