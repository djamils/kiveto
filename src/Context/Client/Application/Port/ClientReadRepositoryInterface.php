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
     * Distinct, non-empty cities of the clinic's clients, alphabetically.
     *
     * @return list<string>
     */
    public function listCities(ClinicId $clinicId): array;

    /**
     * The clients of the clinic, archived ones included, one of whose phones
     * is exactly this number, oldest record first.
     *
     * @return list<string> client ids
     */
    public function findIdsByPhone(ClinicId $clinicId, string $phone): array;

    /**
     * The clients of the clinic, archived ones included, carrying this
     * (normalised, 9-digit) SIREN, oldest record first.
     *
     * @return list<string> client ids
     */
    public function findIdsBySiren(ClinicId $clinicId, string $siren): array;

    /**
     * @param list<string> $clientIds UUID strings
     *
     * @return array<string, string> clientId => fullName
     */
    public function findFullNamesByIds(ClinicId $clinicId, array $clientIds): array;
}
