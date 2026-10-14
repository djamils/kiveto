<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Stock;

use App\Context\Catalog\Application\Query\Catalog\ListCatalog\ArticleListRow;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\CatalogListCriteria;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\CatalogPage;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\ListArticles;
use App\Shared\Application\Bus\QueryBusInterface;

/**
 * What the stock trail needs to know of the catalogue: the trail holds article
 * ids, so searching it by name — or narrowing it on the controlled
 * substances — goes through the catalogue first.
 */
final readonly class StockArticles
{
    private const int WHOLE = 5000;

    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    /**
     * The narcotics of the clinic: what the register of controlled substances
     * lists.
     *
     * @return list<string>
     */
    public function narcotics(string $clinicId): array
    {
        return array_values(array_map(
            static fn (ArticleListRow $article): string => $article->id,
            array_filter($this->rows($clinicId, null), static fn (ArticleListRow $article): bool => 'RX_NARCOTIC' === $article->prescriptionClass),
        ));
    }

    /**
     * @return list<string> the ids of the articles whose name or code match
     */
    public function matching(string $clinicId, string $term): array
    {
        return array_map(static fn (ArticleListRow $article): string => $article->id, $this->rows($clinicId, $term));
    }

    /**
     * @return array<string, ArticleListRow> keyed by id
     */
    public function byId(string $clinicId): array
    {
        $out = [];

        foreach ($this->rows($clinicId, null) as $article) {
            $out[$article->id] = $article;
        }

        return $out;
    }

    /**
     * @return list<ArticleListRow>
     */
    private function rows(string $clinicId, ?string $term): array
    {
        $page = $this->queryBus->ask(new ListArticles($clinicId, new CatalogListCriteria(term: $term, status: null, perPage: self::WHOLE)));

        if (!$page instanceof CatalogPage) {
            return [];
        }

        return array_values(array_filter($page->items, static fn ($item): bool => $item instanceof ArticleListRow));
    }
}
