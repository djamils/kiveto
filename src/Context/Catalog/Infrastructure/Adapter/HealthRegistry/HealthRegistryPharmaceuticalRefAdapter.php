<?php

declare(strict_types=1);

namespace App\Context\Catalog\Infrastructure\Adapter\HealthRegistry;

use App\Context\Catalog\Application\Port\PharmaceuticalRefProviderInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\PharmaceuticalRegistry\Application\Query\GetMarketingAuthorizationByGtin\GetMarketingAuthorizationByGtin;
use App\System\PharmaceuticalRegistry\Application\Query\GetMarketingAuthorizationDetail\GetMarketingAuthorizationDetail;
use App\System\PharmaceuticalRegistry\Application\Query\GetMarketingAuthorizationDetail\MarketingAuthorizationDetailView;
use App\System\PharmaceuticalRegistry\Application\Query\SearchMarketingAuthorizations\MarketingAuthorizationSearchResult;
use App\System\PharmaceuticalRegistry\Application\Query\SearchMarketingAuthorizations\SearchMarketingAuthorizations;

final readonly class HealthRegistryPharmaceuticalRefAdapter implements PharmaceuticalRefProviderInterface
{
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function findById(string $marketingAuthorizationId): ?PharmaceuticalRef
    {
        $view = $this->queryBus->ask(new GetMarketingAuthorizationDetail($marketingAuthorizationId));

        if (!$view instanceof MarketingAuthorizationDetailView) {
            return null;
        }

        return $this->mapDetailView($view);
    }

    public function findByGtin(string $gtin): ?PharmaceuticalRef
    {
        $view = $this->queryBus->ask(new GetMarketingAuthorizationByGtin($gtin));

        if (!$view instanceof MarketingAuthorizationDetailView) {
            return null;
        }

        return $this->mapDetailView($view);
    }

    /** @return list<PharmaceuticalRef> */
    public function searchByName(string $term, int $limit): array
    {
        $results = $this->queryBus->ask(new SearchMarketingAuthorizations(term: $term, limit: $limit));

        if (!\is_array($results)) {
            return [];
        }

        return array_values(array_map(
            static fn (MarketingAuthorizationSearchResult $r) => new PharmaceuticalRef(
                id: $r->id,
                commercialName: $r->commercialName,
                gtin: $r->gtin,
                requiresPrescription: null,
                prescriptionClass: null,
                isControlledSubstance: null,
                status: $r->status,
            ),
            array_filter($results, static fn (mixed $r) => $r instanceof MarketingAuthorizationSearchResult),
        ));
    }

    private function mapDetailView(MarketingAuthorizationDetailView $view): PharmaceuticalRef
    {
        $gtin = null;

        foreach ($view->presentations as $presentation) {
            if (\is_array($presentation) && isset($presentation['gtin']) && \is_string($presentation['gtin'])) {
                $gtin = $presentation['gtin'];
                break;
            }
        }

        return new PharmaceuticalRef(
            id: $view->id,
            commercialName: $view->commercialName,
            gtin: $gtin,
            requiresPrescription: null !== $view->controlledSubstanceClass,
            prescriptionClass: $view->controlledSubstanceClass,
            isControlledSubstance: null !== $view->controlledSubstanceClass,
            status: $view->status,
        );
    }
}
