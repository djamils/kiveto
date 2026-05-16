<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Query\ListMarketingAuthorizationsByTargetSpecies;

use App\System\PharmaceuticalRegistry\Application\Port\MarketingAuthorizationSearchRepositoryInterface;
use App\System\PharmaceuticalRegistry\Application\Query\SearchMarketingAuthorizations\MarketingAuthorizationSearchResult;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\TargetSpeciesCode;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListMarketingAuthorizationsByTargetSpeciesHandler
{
    public function __construct(
        private MarketingAuthorizationSearchRepositoryInterface $searchRepository,
    ) {
    }

    /**
     * @return MarketingAuthorizationSearchResult[]
     */
    public function __invoke(ListMarketingAuthorizationsByTargetSpecies $query): array
    {
        return $this->searchRepository->listByTargetSpecies(TargetSpeciesCode::fromString($query->targetSpeciesCode));
    }
}
