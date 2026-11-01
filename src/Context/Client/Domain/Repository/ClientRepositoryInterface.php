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

    /**
     * Reads the client again and locks its row until the transaction ends
     * (`SELECT … FOR UPDATE`): concurrent consent changes run one after the
     * other. Needs an open transaction.
     *
     * @throws ClientNotFoundException
     */
    public function getForUpdate(ClinicId $clinicId, ClientId $clientId): Client;
}
