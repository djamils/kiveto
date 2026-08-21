<?php

declare(strict_types=1);

namespace App\Context\Consultation\Infrastructure\Adapter\Catalog;

use App\Context\Catalog\Application\Query\Act\GetActDetail\ActDetailView;
use App\Context\Catalog\Application\Query\Act\GetActDetail\GetActDetail;
use App\Context\Catalog\Application\Query\Article\GetArticleDetail\ArticleDetailView;
use App\Context\Catalog\Application\Query\Article\GetArticleDetail\GetArticleDetail;
use App\Context\Catalog\Application\Query\Catalog\SearchCatalogItems\CatalogSearchResult;
use App\Context\Catalog\Application\Query\Catalog\SearchCatalogItems\SearchCatalogItems;
use App\Context\Catalog\Application\Query\Pricing\ResolvePrice\ResolvePrice;
use App\Context\Catalog\Domain\Pricing\ValueObject\ResolvedPrice;
use App\Context\Consultation\Application\Port\CatalogItemDto;
use App\Context\Consultation\Application\Port\CatalogItemProviderInterface;
use App\Context\Consultation\Application\Port\CatalogPriceDto;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\PharmaceuticalRegistry\Application\Query\GetMarketingAuthorizationDetail\GetMarketingAuthorizationDetail;
use App\System\PharmaceuticalRegistry\Application\Query\GetMarketingAuthorizationDetail\MarketingAuthorizationDetailView;

final readonly class QueryBusCatalogItemProvider implements CatalogItemProviderInterface
{
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function search(string $term, string $clinicId, int $limit = 20): array
    {
        $results = $this->queryBus->ask(new SearchCatalogItems(
            clinicId: $clinicId,
            term: $term,
            limit: $limit,
        ));

        if (!\is_array($results)) {
            return [];
        }

        $items = [];

        foreach ($results as $result) {
            if (!$result instanceof CatalogSearchResult) {
                continue;
            }

            $items[] = new CatalogItemDto(
                itemType: $result->type,
                itemId: $result->id,
                name: $result->name,
                code: $result->code,
                requiresPrescription: $result->requiresPrescription,
                basePriceMinorUnits: $result->basePriceMinorUnits,
                currency: $result->basePriceCurrency,
                taxCategoryCode: $result->taxCategoryCode,
                status: $result->status,
            );
        }

        return $items;
    }

    public function detail(string $itemType, string $itemId, string $clinicId): ?CatalogItemDto
    {
        if ('ACT' === $itemType) {
            $view = $this->askQuietly(new GetActDetail(actId: $itemId, clinicId: $clinicId));

            if (!$view instanceof ActDetailView) {
                return null;
            }

            return new CatalogItemDto(
                itemType: 'ACT',
                itemId: $view->id,
                name: $view->name,
                code: $view->code,
                requiresPrescription: false,
                basePriceMinorUnits: $view->basePriceMinorUnits,
                currency: $view->basePriceCurrency,
                taxCategoryCode: $view->taxCategoryCode,
                status: $view->status,
            );
        }

        $view = $this->askQuietly(new GetArticleDetail(articleId: $itemId, clinicId: $clinicId));

        if (!$view instanceof ArticleDetailView) {
            return null;
        }

        return new CatalogItemDto(
            itemType: 'ARTICLE',
            itemId: $view->id,
            name: $view->name,
            code: $view->code,
            requiresPrescription: true === $view->requiresPrescription,
            basePriceMinorUnits: $view->basePriceMinorUnits,
            currency: $view->basePriceCurrency,
            taxCategoryCode: $view->taxCategoryCode,
            status: $view->status,
        );
    }

    public function resolvePrice(CatalogItemDto $item, string $clinicId): CatalogPriceDto
    {
        // Null price list = the clinic's default one.
        $resolved = $this->askQuietly(new ResolvePrice(
            clinicId: $clinicId,
            priceListId: null,
            itemType: $item->itemType,
            itemId: $item->itemId,
        ));

        if (!$resolved instanceof ResolvedPrice) {
            // No price list configured yet: fall back to the item's base price.
            return new CatalogPriceDto(
                minorUnits: $item->basePriceMinorUnits,
                currency: $item->currency,
                taxCategoryCode: $item->taxCategoryCode,
            );
        }

        return new CatalogPriceDto(
            minorUnits: $resolved->netAmount->minorUnits(),
            currency: $resolved->netAmount->currency()->toString(),
            taxCategoryCode: $item->taxCategoryCode,
        );
    }

    public function activeSubstances(string $articleId, string $clinicId): array
    {
        $article = $this->askQuietly(new GetArticleDetail(articleId: $articleId, clinicId: $clinicId));

        if (!$article instanceof ArticleDetailView || null === $article->authorizationRef) {
            return [];
        }

        $authorization = $this->askQuietly(
            new GetMarketingAuthorizationDetail(marketingAuthorizationId: $article->authorizationRef),
        );

        if (!$authorization instanceof MarketingAuthorizationDetailView) {
            return [];
        }

        $labels = [];

        foreach ($authorization->activeSubstances as $substance) {
            if (\is_string($substance) && '' !== $substance) {
                $labels[] = $substance;
            }
        }

        return $labels;
    }

    /**
     * The cockpit degrades gracefully on missing catalog data (archived article,
     * clinic without price list), so a not-found from the Catalog is a null
     * answer here rather than a bubbling exception.
     */
    private function askQuietly(object $query): mixed
    {
        try {
            return $this->queryBus->ask($query);
        } catch (\Throwable) {
            return null;
        }
    }
}
