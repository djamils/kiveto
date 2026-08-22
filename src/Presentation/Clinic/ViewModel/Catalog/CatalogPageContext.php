<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Catalog;

use App\Context\Catalog\Application\Query\Catalog\ListCatalog\CatalogCounters;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\CatalogListCriteria;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\GetCatalogCounters;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\Taxation\Application\Query\ListTaxCategories\ListTaxCategories;
use App\System\Taxation\Domain\TaxCategory;
use Symfony\Component\HttpFoundation\Request;

/**
 * What every tab of the catalogue needs: the criteria read off the query string,
 * the counters of the tab bar, and the tax categories the forms offer.
 *
 * Sorts, statuses and page sizes are whitelisted here rather than passed through
 * — the read repository inlines them into its SQL.
 */
final readonly class CatalogPageContext
{
    /** @var list<int> */
    public const array PAGE_SIZES = [25, 50, 100];

    /** @var list<string> */
    private const array SORTS = [
        CatalogListCriteria::SORT_NAME,
        CatalogListCriteria::SORT_CODE,
        CatalogListCriteria::SORT_PRICE,
        CatalogListCriteria::SORT_CATEGORY,
        CatalogListCriteria::SORT_UPDATED,
    ];

    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    /**
     * @param list<string> $allowedKinds the categories, kinds or pricing modes the tab filters on
     */
    public function criteria(Request $request, array $allowedKinds): CatalogListCriteria
    {
        /** @var array<int, mixed> $rawKinds */
        $rawKinds = $request->query->all('kind');

        $kinds = [];

        foreach ($rawKinds as $kind) {
            if (\is_string($kind) && \in_array($kind, $allowedKinds, true)) {
                $kinds[] = $kind;
            }
        }

        $status = mb_strtoupper(mb_trim($request->query->getString('status', CatalogListCriteria::STATUS_ACTIVE)));
        $sort   = mb_trim($request->query->getString('sort', CatalogListCriteria::SORT_NAME));
        $term   = mb_trim($request->query->getString('q'));

        return new CatalogListCriteria(
            term: '' !== $term ? $term : null,
            kinds: $kinds,
            status: match ($status) {
                CatalogListCriteria::STATUS_ARCHIVED => CatalogListCriteria::STATUS_ARCHIVED,
                'ALL'                                => null,
                default                              => CatalogListCriteria::STATUS_ACTIVE,
            },
            requiresPrescription: match ($request->query->getString('rx')) {
                '1'     => true,
                '0'     => false,
                default => null,
            },
            controlledOnly: '1' === $request->query->getString('controlled') ? true : null,
            sortBy: \in_array($sort, self::SORTS, true) ? $sort : CatalogListCriteria::SORT_NAME,
            sortDescending: 'desc' === mb_strtolower($request->query->getString('dir', 'asc')),
            page: max(1, $request->query->getInt('page', 1)),
            perPage: \in_array($request->query->getInt('per_page', 25), self::PAGE_SIZES, true)
                ? $request->query->getInt('per_page', 25)
                : 25,
        );
    }

    public function counters(string $clinicId): CatalogCounters
    {
        $counters = $this->queryBus->ask(new GetCatalogCounters($clinicId));

        return $counters instanceof CatalogCounters
            ? $counters
            : new CatalogCounters(0, 0, 0, 0, 0, 0, 0);
    }

    /**
     * The tax categories the create and edit forms offer, in the order the
     * Taxation context lists them.
     *
     * @return list<array{code: string, label: string}>
     */
    public function taxCategories(): array
    {
        $categories = $this->queryBus->ask(new ListTaxCategories());

        if (!\is_array($categories)) {
            return [];
        }

        $options = [];

        foreach ($categories as $category) {
            if (!$category instanceof TaxCategory) {
                continue;
            }

            $options[] = [
                'code'  => $category->code()->toString(),
                'label' => $category->displayName(),
            ];
        }

        return $options;
    }

    /**
     * The filters as they came in, so every link can rebuild the query string
     * without losing what the user picked.
     *
     * @return array<string, mixed>
     */
    public function queryState(Request $request): array
    {
        $state = [];

        foreach (['q', 'status', 'sort', 'dir', 'rx', 'controlled'] as $key) {
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
}
