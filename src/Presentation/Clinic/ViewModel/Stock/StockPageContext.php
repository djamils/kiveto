<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Stock;

use App\Context\Catalog\Application\Query\Catalog\ListCatalog\ArticleListRow;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\CatalogListCriteria;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\CatalogPage;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\ListArticles;
use App\Context\Inventory\Application\Query\StockOverview\ListArticleIdsInState;
use App\Context\Inventory\Application\Query\StockOverview\StockSnapshotForArticles;
use App\Context\Inventory\Application\Query\StockOverview\StockSnapshotRow;
use App\Context\Inventory\Application\Query\StockOverview\StockStateFilter;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * The stock page is a page of catalogue articles with the figures Inventory
 * holds for them.
 *
 * Neither context reads the other's tables: Catalog decides which articles are
 * on the page, in what order, and answers the search; Inventory says what is
 * behind them, and — when the page is narrowed on a stock state — which ones are
 * of interest in the first place.
 */
final readonly class StockPageContext
{
    /** @var list<int> */
    public const array PAGE_SIZES = [25, 50, 100];

    /** How far ahead a lot has to expire to be worth flagging. */
    public const int EXPIRING_WITHIN_DAYS = 30;

    /** @var list<string> */
    private const array SORTS = [
        CatalogListCriteria::SORT_NAME,
        CatalogListCriteria::SORT_CODE,
        CatalogListCriteria::SORT_CATEGORY,
    ];

    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    /**
     * The articles of one page, each paired with its stock figures — null when
     * the clinic has never opened a stock item for it.
     *
     * @return array{
     *     page: CatalogPage<ArticleListRow>,
     *     rows: list<array{article: ArticleListRow, stock: StockSnapshotRow|null}>,
     *     state: StockStateFilter|null
     * }
     */
    public function build(Request $request, string $clinicId): array
    {
        $state    = $this->state($request);
        $criteria = $this->criteria($request, $clinicId, $state);

        $page = $this->queryBus->ask(new ListArticles($clinicId, $criteria));
        \assert($page instanceof CatalogPage);

        $articleIds = array_map(
            static fn (ArticleListRow $article): string => $article->id,
            array_values(array_filter($page->items, static fn ($item): bool => $item instanceof ArticleListRow)),
        );

        $snapshots = $this->queryBus->ask(new StockSnapshotForArticles($clinicId, $articleIds));
        $snapshots = \is_array($snapshots) ? $snapshots : [];

        $rows = [];

        foreach ($page->items as $article) {
            \assert($article instanceof ArticleListRow);

            $stock = $snapshots[$article->id] ?? null;

            $rows[] = [
                'article' => $article,
                'stock'   => $stock instanceof StockSnapshotRow ? $stock : null,
            ];
        }

        return ['page' => $page, 'rows' => $rows, 'state' => $state];
    }

    /**
     * The names behind a set of article ids, for the pages that list something
     * other than articles — the movement trail, mostly.
     *
     * @param list<string> $articleIds
     *
     * @return array<string, ArticleListRow>
     */
    public function articlesById(string $clinicId, array $articleIds): array
    {
        if ([] === $articleIds) {
            return [];
        }

        $page = $this->queryBus->ask(new ListArticles($clinicId, new CatalogListCriteria(
            status: null,
            articleIds: array_values(array_unique($articleIds)),
            perPage: 100,
        )));

        if (!$page instanceof CatalogPage) {
            return [];
        }

        $byId = [];

        foreach ($page->items as $article) {
            if ($article instanceof ArticleListRow) {
                $byId[$article->id] = $article;
            }
        }

        return $byId;
    }

    /**
     * The article ids whose name or code matches a term, so a list that is not
     * driven by the catalogue can still be searched by article.
     *
     * @return list<string>|null null when nothing was searched
     */
    public function articleIdsMatching(string $clinicId, string $term): ?array
    {
        if ('' === $term) {
            return null;
        }

        $page = $this->queryBus->ask(new ListArticles($clinicId, new CatalogListCriteria(
            term: $term,
            status: null,
            perPage: 100,
        )));

        if (!$page instanceof CatalogPage) {
            return [];
        }

        return array_values(array_map(
            static fn (ArticleListRow $article): string => $article->id,
            array_filter($page->items, static fn ($item): bool => $item instanceof ArticleListRow),
        ));
    }

    public function state(Request $request): ?StockStateFilter
    {
        return StockStateFilter::tryFrom(mb_strtoupper(mb_trim($request->query->getString('state'))));
    }

    /**
     * The filters as they came in, so every link rebuilds the query string
     * without losing what the user picked.
     *
     * @return array<string, mixed>
     */
    public function queryState(Request $request): array
    {
        $state = [];

        foreach (['q', 'state', 'sort', 'dir', 'type', 'from', 'to'] as $key) {
            $value = mb_trim($request->query->getString($key));

            if ('' !== $value) {
                $state[$key] = $value;
            }
        }

        /** @var array<int, mixed> $kinds */
        $kinds = $request->query->all('kind');

        if ([] !== $kinds) {
            $state['kind'] = array_values(array_filter($kinds, \is_string(...)));
        }

        $perPage = $request->query->getInt('per_page', 25);

        if (25 !== $perPage) {
            $state['per_page'] = $perPage;
        }

        return $state;
    }

    public function perPage(Request $request): int
    {
        $perPage = $request->query->getInt('per_page', 25);

        return \in_array($perPage, self::PAGE_SIZES, true) ? $perPage : 25;
    }

    /**
     * @param list<string> $allowedKinds
     *
     * @return list<string>
     */
    public function kinds(Request $request, array $allowedKinds): array
    {
        /** @var array<int, mixed> $raw */
        $raw   = $request->query->all('kind');
        $kinds = [];

        foreach ($raw as $kind) {
            if (\is_string($kind) && \in_array($kind, $allowedKinds, true)) {
                $kinds[] = $kind;
            }
        }

        return $kinds;
    }

    private function criteria(Request $request, string $clinicId, ?StockStateFilter $state): CatalogListCriteria
    {
        $sort = mb_trim($request->query->getString('sort', CatalogListCriteria::SORT_NAME));
        $term = mb_trim($request->query->getString('q'));

        // A stock state is decided by Inventory; the catalogue page is then
        // narrowed to the articles it named.
        $articleIds = null;

        if (null !== $state) {
            $ids        = $this->queryBus->ask(new ListArticleIdsInState($clinicId, $state, self::EXPIRING_WITHIN_DAYS));
            $articleIds = \is_array($ids)
                ? array_values(array_filter($ids, \is_string(...)))
                : [];
        }

        return new CatalogListCriteria(
            term: '' !== $term ? $term : null,
            kinds: $this->kinds($request, ['DRUG', 'CONSUMABLE', 'FOOD', 'EQUIPMENT']),
            status: CatalogListCriteria::STATUS_ACTIVE,
            trackStockOnly: true,
            articleIds: $articleIds,
            sortBy: \in_array($sort, self::SORTS, true) ? $sort : CatalogListCriteria::SORT_NAME,
            sortDescending: 'desc' === mb_strtolower($request->query->getString('dir', 'asc')),
            page: max(1, $request->query->getInt('page', 1)),
            perPage: $this->perPage($request),
        );
    }
}
