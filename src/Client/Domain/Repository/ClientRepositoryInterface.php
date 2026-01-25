<?php

declare(strict_types=1);

namespace App\Client\Domain\Repository;

use App\Client\Domain\Client;
use App\Client\Domain\Exception\ClientNotFoundException;
use App\Client\Domain\ValueObject\ClientId;
use App\Clinic\Domain\ValueObject\ClinicId;

interface ClientRepositoryInterface
{
    public function save(Client $client): void;

    /**
     * @throws ClientNotFoundException
     */
    public function get(ClinicId $clinicId, ClientId $clientId): Client;

    public function find(ClinicId $clinicId, ClientId $clientId): ?Client;

    public function nextId(): ClientId;
}
