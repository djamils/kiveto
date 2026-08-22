<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Port;

use App\Context\Catalog\Application\Query\Catalog\ListCatalog\ActListRow;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\ArticleListRow;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\CatalogCounters;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\CatalogListCriteria;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\PackageComponentRow;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\PackageListRow;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\PriceListEntryRow;
use App\Context\Catalog\Domain\ValueObject\ClinicId;

/**
 * The listing side of the catalogue: pages of what the clinic sells, filtered
 * and counted, with the references of packages and price lists resolved to the
 * items they point at.
 *
 * The aggregates answer "what is this act"; this answers "what is on offer".
 */
interface CatalogListReadRepositoryInterface
{
    /**
     * @return array{items: list<ActListRow>, total: int}
     */
    public function acts(ClinicId $clinicId, CatalogListCriteria $criteria): array;

    /**
     * @return array{items: list<ArticleListRow>, total: int}
     */
    public function articles(ClinicId $clinicId, CatalogListCriteria $criteria): array;

    /**
     * @return array{items: list<PackageListRow>, total: int}
     */
    public function packages(ClinicId $clinicId, CatalogListCriteria $criteria): array;

    /**
     * The components of one package, in the order they are charged.
     *
     * @return list<PackageComponentRow>
     */
    public function packageComponents(ClinicId $clinicId, string $packageId): array;

    /**
     * The overrides of one price list, resolved against the items they apply to.
     *
     * @return list<PriceListEntryRow>
     */
    public function priceListEntries(ClinicId $clinicId, string $priceListId): array;

    public function counters(ClinicId $clinicId): CatalogCounters;
}
