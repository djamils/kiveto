<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Query\ListMarketingAuthorizationsByActiveSubstance;

use App\System\PharmaceuticalRegistry\Application\Port\MarketingAuthorizationSearchRepositoryInterface;
use App\System\PharmaceuticalRegistry\Application\Query\SearchMarketingAuthorizations\MarketingAuthorizationSearchResult;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ActiveSubstanceId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListMarketingAuthorizationsByActiveSubstanceHandler
{
    public function __construct(
        private MarketingAuthorizationSearchRepositoryInterface $searchRepository,
    ) {
    }

    /**
     * @return MarketingAuthorizationSearchResult[]
     */
    public function __invoke(ListMarketingAuthorizationsByActiveSubstance $query): array
    {
        return $this->searchRepository->listBySubstance(ActiveSubstanceId::fromString($query->activeSubstanceId));
    }
}
