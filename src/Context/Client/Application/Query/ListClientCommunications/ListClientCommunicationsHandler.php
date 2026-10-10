<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\ListClientCommunications;

use App\Context\Client\Application\Port\ClientCommunicationReadRepositoryInterface;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListClientCommunicationsHandler
{
    public function __construct(
        private ClientCommunicationReadRepositoryInterface $readRepository,
    ) {
    }

    /**
     * @return list<ClientCommunicationView>
     */
    public function __invoke(ListClientCommunications $query): array
    {
        return $this->readRepository->listForClient(
            ClinicId::fromString($query->clinicId),
            ClientId::fromString($query->clientId),
        );
    }
}
