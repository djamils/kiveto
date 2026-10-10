<?php

declare(strict_types=1);

namespace App\Context\Client\Domain\Repository;

use App\Context\Client\Domain\ClientCommunication;

interface ClientCommunicationRepositoryInterface
{
    public function save(ClientCommunication $clientCommunication): void;
}
