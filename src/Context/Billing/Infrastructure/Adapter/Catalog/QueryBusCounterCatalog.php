<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Adapter\Catalog;

use App\Context\Billing\Application\Port\CounterArticleDto;
use App\Context\Billing\Application\Port\CounterCatalogInterface;
use App\Context\Catalog\Application\Query\Article\GetArticleDetail\ArticleDetailView;
use App\Context\Catalog\Application\Query\Article\GetArticleDetail\GetArticleDetail;
use App\Context\Catalog\Application\Query\Catalog\SearchCatalogItems\CatalogSearchResult;
use App\Context\Catalog\Application\Query\Catalog\SearchCatalogItems\SearchCatalogItems;
use App\Context\Catalog\Application\Query\Pricing\ResolvePrice\ResolvePrice;
use App\Context\Catalog\Domain\Pricing\ValueObject\ResolvedPrice;
use App\Shared\Application\Bus\QueryBusInterface;

/**
 * Reads the counter's articles from the Catalog: active articles that need no
 * prescription, at the clinic's default price list (their base price when the
 * clinic has none).
 */
final readonly class QueryBusCounterCatalog implements CounterCatalogInterface
{
    /** The catalogue search returns acts and articles half and half: ask for enough of both. */
    private const int SEARCH_FACTOR = 4;

    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function search(string $clinicId, string $term, int $limit): array
    {
        $results  = $this->ask(new SearchCatalogItems(clinicId: $clinicId, term: $term, limit: $limit * self::SEARCH_FACTOR));
        $articles = [];

        foreach (\is_array($results) ? $results : [] as $result) {
            if (!$result instanceof CatalogSearchResult || 'ARTICLE' !== $result->type || 'ACTIVE' !== $result->status || $result->requiresPrescription) {
                continue;
            }

            $articles[] = new CounterArticleDto(
                articleId: $result->id,
                label: $result->name,
                code: $result->code,
                unitNetMinorUnits: $this->price($clinicId, $result->id, $result->basePriceMinorUnits),
                currency: $result->basePriceCurrency,
                taxCategoryCode: $result->taxCategoryCode,
            );
        }

        usort($articles, static fn (CounterArticleDto $a, CounterArticleDto $b): int => strcmp($a->label, $b->label));

        return \array_slice($articles, 0, $limit);
    }

    public function article(string $clinicId, string $articleId): ?CounterArticleDto
    {
        $view = $this->ask(new GetArticleDetail(articleId: $articleId, clinicId: $clinicId));

        if (!$view instanceof ArticleDetailView || 'ACTIVE' !== $view->status || true === $view->requiresPrescription) {
            return null;
        }

        return new CounterArticleDto(
            articleId: $view->id,
            label: $view->name,
            code: $view->code,
            unitNetMinorUnits: $this->price($clinicId, $view->id, $view->basePriceMinorUnits),
            currency: $view->basePriceCurrency,
            taxCategoryCode: $view->taxCategoryCode,
        );
    }

    private function price(string $clinicId, string $articleId, int $basePrice): int
    {
        $resolved = $this->ask(new ResolvePrice(clinicId: $clinicId, priceListId: null, itemType: 'ARTICLE', itemId: $articleId));

        return $resolved instanceof ResolvedPrice ? $resolved->netAmount->minorUnits() : $basePrice;
    }

    /**
     * A missing article or price list is an empty answer at the counter, not an error.
     */
    private function ask(object $query): mixed
    {
        try {
            return $this->queryBus->ask($query);
        } catch (\Throwable) {
            return null;
        }
    }
}
