<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Port;

use App\Context\Client\Application\Query\GetClientById\ClientView;
use App\Context\Client\Application\Query\SearchClients\ClientListItemView;
use App\Context\Client\Application\Query\SearchClients\SearchClientsCriteria;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClinicId;

interface ClientReadRepositoryInterface
{
    public function findById(ClinicId $clinicId, ClientId $clientId): ?ClientView;

    /**
     * @return array{items: list<ClientListItemView>, total: int}
     */
    public function search(ClinicId $clinicId, SearchClientsCriteria $criteria): array;

    public function countBy(ClinicId $clinicId, SearchClientsCriteria $criteria): int;

    /**
     * Returns true if any client in the given clinic already uses this email
     * in one of their contact methods.
     */
    public function emailExistsInClinic(ClinicId $clinicId, string $email): bool;

    /**
     * @param list<string> $clientIds UUID strings
     *
     * @return array<string, string> clientId => fullName
     */
    public function findFullNamesByIds(ClinicId $clinicId, array $clientIds): array;
}
