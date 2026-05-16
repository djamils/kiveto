<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Query\SearchMarketingAuthorizations;

use App\System\PharmaceuticalRegistry\Application\Port\MarketingAuthorizationSearchRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SearchMarketingAuthorizationsHandler
{
    public function __construct(
        private MarketingAuthorizationSearchRepositoryInterface $searchRepository,
    ) {
    }

    /**
     * @return MarketingAuthorizationSearchResult[]
     */
    public function __invoke(SearchMarketingAuthorizations $query): array
    {
        return $this->searchRepository->search($query->term, $query->limit, $query->filters);
    }
}
