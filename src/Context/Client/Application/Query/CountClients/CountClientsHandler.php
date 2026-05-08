<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\CountClients;

use App\Context\Client\Application\Port\ClientReadRepositoryInterface;
use App\Context\Client\Application\Query\SearchClients\SearchClientsCriteria;
use App\Context\Client\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CountClientsHandler
{
    public function __construct(
        private ClientReadRepositoryInterface $clientReadRepository,
    ) {
    }

    public function __invoke(CountClients $query): int
    {
        $clinicId = ClinicId::fromString($query->clinicId);

        $criteria = new SearchClientsCriteria(
            searchTerm: $query->searchTerm,
            status: $query->status,
        );

        return $this->clientReadRepository->countBy($clinicId, $criteria);
    }
}
