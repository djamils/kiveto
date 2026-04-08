<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\SearchClients;

use App\Context\Client\Application\Port\ClientReadRepositoryInterface;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SearchClientsHandler
{
    public function __construct(
        private ClientReadRepositoryInterface $clientReadRepository,
    ) {
    }

    /**
     * @return array{items: list<ClientListItemView>, total: int}
     */
    public function __invoke(SearchClients $query): array
    {
        $clinicId = ClinicId::fromString($query->clinicId);

        $criteria = new SearchClientsCriteria(
            searchTerm: $query->searchTerm,
            status: $query->status,
            page: $query->page,
            limit: $query->limit,
        );

        return $this->clientReadRepository->search($clinicId, $criteria);
    }
}
