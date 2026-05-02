<?php

declare(strict_types=1);

namespace App\Context\Client\Domain\Repository;

use App\Context\Client\Domain\Client;
use App\Context\Client\Domain\Exception\ClientNotFoundException;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClinicId;

interface ClientRepositoryInterface
{
    public function save(Client $client): void;

    /**
     * @throws ClientNotFoundException
     */
    public function get(ClinicId $clinicId, ClientId $clientId): Client;

    public function find(ClinicId $clinicId, ClientId $clientId): ?Client;
}
