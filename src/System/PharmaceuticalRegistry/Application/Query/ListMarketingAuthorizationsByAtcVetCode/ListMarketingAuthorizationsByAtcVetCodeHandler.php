<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Query\ListMarketingAuthorizationsByAtcVetCode;

use App\System\PharmaceuticalRegistry\Application\Port\MarketingAuthorizationSearchRepositoryInterface;
use App\System\PharmaceuticalRegistry\Application\Query\SearchMarketingAuthorizations\MarketingAuthorizationSearchResult;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListMarketingAuthorizationsByAtcVetCodeHandler
{
    public function __construct(
        private MarketingAuthorizationSearchRepositoryInterface $searchRepository,
    ) {
    }

    /**
     * @return MarketingAuthorizationSearchResult[]
     */
    public function __invoke(ListMarketingAuthorizationsByAtcVetCode $query): array
    {
        return $this->searchRepository->listByAtcVet($query->atcVetCode);
    }
}
