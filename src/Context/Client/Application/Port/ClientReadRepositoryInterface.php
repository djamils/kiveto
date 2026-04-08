<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Port;

use App\Context\Client\Application\Query\GetClientById\ClientView;
use App\Context\Client\Application\Query\SearchClients\ClientListItemView;
use App\Context\Client\Application\Query\SearchClients\SearchClientsCriteria;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Clinic\Domain\ValueObject\ClinicId;

interface ClientReadRepositoryInterface
{
    public function findById(ClinicId $clinicId, ClientId $clientId): ?ClientView;

    /**
     * @return array{items: list<ClientListItemView>, total: int}
     */
    public function search(ClinicId $clinicId, SearchClientsCriteria $criteria): array;
}
