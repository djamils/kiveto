<?php

declare(strict_types=1);

namespace App\Context\Consultation\Infrastructure\Adapter\Catalog;

use App\Context\Catalog\Application\Query\Catalog\ListCatalog\ActListRow;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\CatalogListCriteria;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\CatalogPage;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\ListActs;
use App\Context\Consultation\Application\Port\ActCategoryProviderInterface;
use App\Shared\Application\Bus\QueryBusInterface;

/**
 * Asks the Catalog context for the acts of some categories, archived ones
 * included: an act withdrawn since still tells what an old visit was.
 */
final readonly class QueryBusActCategoryProvider implements ActCategoryProviderInterface
{
    /**
     * More acts than a clinic's catalogue holds in a category.
     */
    private const int MAX_ACTS = 500;

    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function actCodesByCategory(string $clinicId, array $categories): array
    {
        if ([] === $categories) {
            return [];
        }

        $page = $this->queryBus->ask(new ListActs($clinicId, new CatalogListCriteria(
            kinds: $categories,
            status: null,
            perPage: self::MAX_ACTS,
        )));

        $codes = [];

        if ($page instanceof CatalogPage) {
            foreach ($page->items as $act) {
                if ($act instanceof ActListRow) {
                    $codes[$act->code] = $act->category;
                }
            }
        }

        return $codes;
    }
}
