<?php

declare(strict_types=1);

namespace App\Client\Application\Port;

use App\Client\Application\Query\GetClientById\ClientView;
use App\Client\Application\Query\SearchClients\ClientListItemView;
use App\Client\Application\Query\SearchClients\SearchClientsCriteria;
use App\Client\Domain\ValueObject\ClientId;
use App\Clinic\Domain\ValueObject\ClinicId;

interface ClientReadRepositoryInterface
{
    public function findById(ClinicId $clinicId, ClientId $clientId): ?ClientView;

    /**
     * @return array{items: list<ClientListItemView>, total: int}
     */
    public function search(ClinicId $clinicId, SearchClientsCriteria $criteria): array;
}
