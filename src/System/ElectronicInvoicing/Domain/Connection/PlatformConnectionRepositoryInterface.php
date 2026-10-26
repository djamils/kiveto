<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Domain\Connection;

use App\Shared\Domain\ValueObject\Siren;

interface PlatformConnectionRepositoryInterface
{
    public function save(PlatformConnection $connection): void;

    public function get(PlatformConnectionId $id): PlatformConnection;

    public function byOrganisation(Siren $organisationId): ?PlatformConnection;

    /**
     * @return list<PlatformConnection> the connections the synchronisation polls
     */
    public function polled(): array;
}
