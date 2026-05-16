<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Port;

use App\System\PharmaceuticalRegistry\Application\Query\SearchMarketingAuthorizations\MarketingAuthorizationSearchResult;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ActiveSubstanceId;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\TargetSpeciesCode;

interface MarketingAuthorizationSearchRepositoryInterface
{
    /**
     * @param array<string, mixed> $filters
     *
     * @return MarketingAuthorizationSearchResult[]
     */
    public function search(string $term, int $limit, array $filters = []): array;

    /**
     * @return MarketingAuthorizationSearchResult[]
     */
    public function listBySubstance(ActiveSubstanceId $activeSubstanceId): array;

    /**
     * @return MarketingAuthorizationSearchResult[]
     */
    public function listByAtcVet(string $codeOrGroup): array;

    /**
     * @return MarketingAuthorizationSearchResult[]
     */
    public function listByTargetSpecies(TargetSpeciesCode $speciesCode): array;
}
