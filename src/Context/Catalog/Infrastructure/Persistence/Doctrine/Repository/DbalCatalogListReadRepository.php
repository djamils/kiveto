<?php

declare(strict_types=1);

namespace App\Context\Catalog\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Catalog\Application\Port\CatalogListReadRepositoryInterface;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\ActListRow;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\ArticleListRow;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\CatalogCounters;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\CatalogListCriteria;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\PackageComponentRow;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\PackageListRow;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\PriceListEntryRow;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * The listing side of the catalogue, read straight from the tables.
 *
 * Packages and price lists point at their items by type and id rather than by
 * foreign key — an override can outlive the act it applied to — so the joins are
 * left joins and a row whose target is gone comes back with a null name rather
 * than disappearing.
 */
final readonly class DbalCatalogListReadRepository implements CatalogListReadRepositoryInterface
{
    /** Sort keys of the acts list, mapped to their SQL expression. */
    private const array ACT_SORTS = [
        CatalogListCriteria::SORT_NAME     => 'a.name',
        CatalogListCriteria::SORT_CODE     => 'a.code',
        CatalogListCriteria::SORT_PRICE    => 'a.base_price_minor_units',
        CatalogListCriteria::SORT_CATEGORY => 'a.category',
        CatalogListCriteria::SORT_UPDATED  => 'a.updated_at',
    ];

    /** Sort keys of the articles list. */
    private const array ARTICLE_SORTS = [
        CatalogListCriteria::SORT_NAME     => 'a.name',
        CatalogListCriteria::SORT_CODE     => 'a.code',
        CatalogListCriteria::SORT_PRICE    => 'a.base_price_minor_units',
        CatalogListCriteria::SORT_CATEGORY => 'a.kind',
        CatalogListCriteria::SORT_UPDATED  => 'a.updated_at',
    ];

    /** Sort keys of the packages list. */
    private const array PACKAGE_SORTS = [
        CatalogListCriteria::SORT_NAME     => 'p.name',
        CatalogListCriteria::SORT_CODE     => 'p.code',
        CatalogListCriteria::SORT_PRICE    => 'p.fixed_price_minor_units',
        CatalogListCriteria::SORT_CATEGORY => 'p.pricing_mode',
        CatalogListCriteria::SORT_UPDATED  => 'p.updated_at',
    ];

    public function __construct(private Connection $connection)
    {
    }

    public function acts(ClinicId $clinicId, CatalogListCriteria $criteria): array
    {
        $filter = $this->baseFilter($clinicId, $criteria, 'a');

        if ([] !== $criteria->kinds) {
            $filter['where'][]              = 'a.category IN (:categories)';
            $filter['params']['categories'] = $criteria->kinds;
            $filter['types']['categories']  = ArrayParameterType::STRING;
        }

        $where = implode(' AND ', $filter['where']);
        $total = $this->count('catalog__acts a', $where, $filter['params'], $filter['types']);

        if (0 === $total) {
            return ['items' => [], 'total' => 0];
        }

        $rows = $this->connection->fetchAllAssociative(
            \sprintf(
                'SELECT BIN_TO_UUID(a.id) AS id, a.code, a.name, a.description, a.category,
                        a.tax_category_code, a.base_price_minor_units, a.base_price_currency,
                        a.estimated_duration_minutes, a.requires_anesthesia, a.status
                 FROM catalog__acts a
                 WHERE %s
                 ORDER BY %s, a.name ASC
                 LIMIT %d OFFSET %d',
                $where,
                $this->order(self::ACT_SORTS, $criteria),
                // Both come from a whitelist in the controller, never from raw
                // input, and MySQL refuses a bound parameter here anyway.
                $criteria->perPage,
                $criteria->offset(),
            ),
            $filter['params'],
            $filter['types'],
        );

        $items = array_map(
            static fn (array $row): ActListRow => new ActListRow(
                id: RowAccessor::string($row, 'id'),
                code: RowAccessor::string($row, 'code'),
                name: RowAccessor::string($row, 'name'),
                description: RowAccessor::nullableString($row, 'description'),
                category: RowAccessor::string($row, 'category'),
                taxCategoryCode: RowAccessor::string($row, 'tax_category_code'),
                basePriceMinorUnits: RowAccessor::int($row, 'base_price_minor_units'),
                currency: RowAccessor::string($row, 'base_price_currency'),
                estimatedDurationMinutes: RowAccessor::int($row, 'estimated_duration_minutes'),
                requiresAnesthesia: (bool) RowAccessor::int($row, 'requires_anesthesia'),
                status: RowAccessor::string($row, 'status'),
            ),
            $rows,
        );

        return ['items' => $items, 'total' => $total];
    }

    public function articles(ClinicId $clinicId, CatalogListCriteria $criteria): array
    {
        $filter = $this->baseFilter($clinicId, $criteria, 'a');

        if ([] !== $criteria->kinds) {
            $filter['where'][]         = 'a.kind IN (:kinds)';
            $filter['params']['kinds'] = $criteria->kinds;
            $filter['types']['kinds']  = ArrayParameterType::STRING;
        }

        if (null !== $criteria->requiresPrescription) {
            $filter['where'][] = $criteria->requiresPrescription
                ? 'a.drug_requires_rx = 1'
                : '(a.drug_requires_rx = 0 OR a.drug_requires_rx IS NULL)';
        }

        if (true === $criteria->controlledOnly) {
            $filter['where'][] = 'a.drug_is_controlled = 1';
        }

        $where = implode(' AND ', $filter['where']);
        $total = $this->count('catalog__articles a', $where, $filter['params'], $filter['types']);

        if (0 === $total) {
            return ['items' => [], 'total' => 0];
        }

        $rows = $this->connection->fetchAllAssociative(
            \sprintf(
                'SELECT BIN_TO_UUID(a.id) AS id, a.code, a.name, a.kind, a.gtin,
                        a.tax_category_code, a.base_price_minor_units, a.base_price_currency,
                        a.unit_of_measure, a.track_stock, BIN_TO_UUID(a.drug_auth_ref) AS drug_auth_ref,
                        a.drug_requires_rx, a.drug_prescription_class, a.drug_is_controlled, a.status
                 FROM catalog__articles a
                 WHERE %s
                 ORDER BY %s, a.name ASC
                 LIMIT %d OFFSET %d',
                $where,
                $this->order(self::ARTICLE_SORTS, $criteria),
                $criteria->perPage,
                $criteria->offset(),
            ),
            $filter['params'],
            $filter['types'],
        );

        $items = array_map(
            static fn (array $row): ArticleListRow => new ArticleListRow(
                id: RowAccessor::string($row, 'id'),
                code: RowAccessor::string($row, 'code'),
                name: RowAccessor::string($row, 'name'),
                kind: RowAccessor::string($row, 'kind'),
                gtin: RowAccessor::nullableString($row, 'gtin'),
                taxCategoryCode: RowAccessor::string($row, 'tax_category_code'),
                basePriceMinorUnits: RowAccessor::int($row, 'base_price_minor_units'),
                currency: RowAccessor::string($row, 'base_price_currency'),
                unitOfMeasure: RowAccessor::string($row, 'unit_of_measure'),
                trackStock: (bool) RowAccessor::int($row, 'track_stock'),
                authorizationRef: RowAccessor::nullableString($row, 'drug_auth_ref'),
                requiresPrescription: 1 === RowAccessor::nullableInt($row, 'drug_requires_rx'),
                prescriptionClass: RowAccessor::nullableString($row, 'drug_prescription_class'),
                isControlledSubstance: 1 === RowAccessor::nullableInt($row, 'drug_is_controlled'),
                status: RowAccessor::string($row, 'status'),
            ),
            $rows,
        );

        return ['items' => $items, 'total' => $total];
    }

    public function packages(ClinicId $clinicId, CatalogListCriteria $criteria): array
    {
        $filter = $this->baseFilter($clinicId, $criteria, 'p');

        if ([] !== $criteria->kinds) {
            $filter['where'][]         = 'p.pricing_mode IN (:modes)';
            $filter['params']['modes'] = $criteria->kinds;
            $filter['types']['modes']  = ArrayParameterType::STRING;
        }

        $where = implode(' AND ', $filter['where']);
        $total = $this->count('catalog__packages p', $where, $filter['params'], $filter['types']);

        if (0 === $total) {
            return ['items' => [], 'total' => 0];
        }

        $rows = $this->connection->fetchAllAssociative(
            \sprintf(
                'SELECT BIN_TO_UUID(p.id) AS id, p.code, p.name, p.tax_category_code,
                        p.pricing_mode, p.fixed_price_minor_units, p.fixed_price_currency, p.status,
                        (SELECT COUNT(*) FROM catalog__package_components c WHERE c.package_id = p.id)
                            AS component_count,
                        COALESCE((
                            SELECT SUM(c.quantity * COALESCE(act.base_price_minor_units, art.base_price_minor_units, 0))
                            FROM catalog__package_components c
                            LEFT JOIN catalog__acts act ON c.item_type = \'ACT\' AND act.id = UUID_TO_BIN(c.item_id)
                            LEFT JOIN catalog__articles art ON c.item_type = \'ARTICLE\' AND art.id = UUID_TO_BIN(c.item_id)
                            WHERE c.package_id = p.id
                        ), 0) AS components_total
                 FROM catalog__packages p
                 WHERE %s
                 ORDER BY %s, p.name ASC
                 LIMIT %d OFFSET %d',
                $where,
                $this->order(self::PACKAGE_SORTS, $criteria),
                $criteria->perPage,
                $criteria->offset(),
            ),
            $filter['params'],
            $filter['types'],
        );

        $items = array_map(
            static fn (array $row): PackageListRow => new PackageListRow(
                id: RowAccessor::string($row, 'id'),
                code: RowAccessor::string($row, 'code'),
                name: RowAccessor::string($row, 'name'),
                taxCategoryCode: RowAccessor::string($row, 'tax_category_code'),
                pricingMode: RowAccessor::string($row, 'pricing_mode'),
                fixedPriceMinorUnits: RowAccessor::nullableInt($row, 'fixed_price_minor_units'),
                currency: RowAccessor::nullableString($row, 'fixed_price_currency') ?? 'EUR',
                componentCount: (int) RowAccessor::string($row, 'component_count'),
                componentsTotalMinorUnits: (int) RowAccessor::string($row, 'components_total'),
                status: RowAccessor::string($row, 'status'),
            ),
            $rows,
        );

        return ['items' => $items, 'total' => $total];
    }

    public function packageComponents(ClinicId $clinicId, string $packageId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT BIN_TO_UUID(c.id) AS id, c.item_type, c.item_id, c.quantity, c.weight,
                    COALESCE(act.code, art.code) AS item_code,
                    COALESCE(act.name, art.name) AS item_name,
                    COALESCE(act.base_price_minor_units, art.base_price_minor_units) AS unit_price,
                    COALESCE(act.base_price_currency, art.base_price_currency) AS currency
             FROM catalog__package_components c
             JOIN catalog__packages p ON p.id = c.package_id
             LEFT JOIN catalog__acts act ON c.item_type = \'ACT\' AND act.id = UUID_TO_BIN(c.item_id)
             LEFT JOIN catalog__articles art ON c.item_type = \'ARTICLE\' AND art.id = UUID_TO_BIN(c.item_id)
             WHERE p.tenant_id = :clinicId AND p.id = UUID_TO_BIN(:packageId)
             ORDER BY c.weight ASC, item_name ASC',
            [
                'clinicId'  => Uuid::fromString($clinicId->toString())->toBinary(),
                'packageId' => $packageId,
            ],
        );

        return array_map(
            static fn (array $row): PackageComponentRow => new PackageComponentRow(
                id: RowAccessor::string($row, 'id'),
                itemType: RowAccessor::string($row, 'item_type'),
                itemId: RowAccessor::string($row, 'item_id'),
                itemCode: RowAccessor::nullableString($row, 'item_code'),
                itemName: RowAccessor::nullableString($row, 'item_name'),
                unitPriceMinorUnits: RowAccessor::nullableInt($row, 'unit_price'),
                currency: RowAccessor::nullableString($row, 'currency') ?? 'EUR',
                quantity: RowAccessor::int($row, 'quantity'),
                weight: RowAccessor::int($row, 'weight'),
            ),
            $rows,
        );
    }

    public function priceListEntries(ClinicId $clinicId, string $priceListId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT BIN_TO_UUID(i.id) AS id, i.item_type, i.item_id,
                    i.net_price_minor_units, i.net_price_currency, i.tax_category_override,
                    COALESCE(act.code, art.code) AS item_code,
                    COALESCE(act.name, art.name) AS item_name,
                    COALESCE(act.base_price_minor_units, art.base_price_minor_units) AS base_price,
                    COALESCE(act.tax_category_code, art.tax_category_code) AS item_tax_category
             FROM catalog__price_list_items i
             JOIN catalog__price_lists l ON l.id = i.price_list_id
             LEFT JOIN catalog__acts act ON i.item_type = \'ACT\' AND act.id = UUID_TO_BIN(i.item_id)
             LEFT JOIN catalog__articles art ON i.item_type = \'ARTICLE\' AND art.id = UUID_TO_BIN(i.item_id)
             WHERE l.tenant_id = :clinicId AND l.id = UUID_TO_BIN(:priceListId)
             ORDER BY item_name ASC',
            [
                'clinicId'    => Uuid::fromString($clinicId->toString())->toBinary(),
                'priceListId' => $priceListId,
            ],
        );

        return array_map(
            static fn (array $row): PriceListEntryRow => new PriceListEntryRow(
                id: RowAccessor::string($row, 'id'),
                itemType: RowAccessor::string($row, 'item_type'),
                itemId: RowAccessor::string($row, 'item_id'),
                itemCode: RowAccessor::nullableString($row, 'item_code'),
                itemName: RowAccessor::nullableString($row, 'item_name'),
                basePriceMinorUnits: RowAccessor::nullableInt($row, 'base_price'),
                netPriceMinorUnits: RowAccessor::int($row, 'net_price_minor_units'),
                currency: RowAccessor::string($row, 'net_price_currency'),
                taxCategoryOverride: RowAccessor::nullableString($row, 'tax_category_override'),
                itemTaxCategoryCode: RowAccessor::nullableString($row, 'item_tax_category'),
            ),
            $rows,
        );
    }

    public function counters(ClinicId $clinicId): CatalogCounters
    {
        $row = $this->connection->fetchAssociative(
            "SELECT
                (SELECT COUNT(*) FROM catalog__acts WHERE tenant_id = :clinicId AND status = 'ACTIVE') AS active_acts,
                (SELECT COUNT(*) FROM catalog__acts WHERE tenant_id = :clinicId AND status = 'ARCHIVED') AS archived_acts,
                (SELECT COUNT(*) FROM catalog__articles WHERE tenant_id = :clinicId AND status = 'ACTIVE') AS active_articles,
                (SELECT COUNT(*) FROM catalog__articles WHERE tenant_id = :clinicId AND status = 'ARCHIVED') AS archived_articles,
                (SELECT COUNT(*) FROM catalog__packages WHERE tenant_id = :clinicId AND status = 'ACTIVE') AS active_packages,
                (SELECT COUNT(*) FROM catalog__packages WHERE tenant_id = :clinicId AND status = 'ARCHIVED') AS archived_packages,
                (SELECT COUNT(*) FROM catalog__price_lists WHERE tenant_id = :clinicId) AS price_lists",
            ['clinicId' => Uuid::fromString($clinicId->toString())->toBinary()],
        );

        if (false === $row) {
            return new CatalogCounters(0, 0, 0, 0, 0, 0, 0);
        }

        return new CatalogCounters(
            activeActs: (int) RowAccessor::string($row, 'active_acts'),
            archivedActs: (int) RowAccessor::string($row, 'archived_acts'),
            activeArticles: (int) RowAccessor::string($row, 'active_articles'),
            archivedArticles: (int) RowAccessor::string($row, 'archived_articles'),
            activePackages: (int) RowAccessor::string($row, 'active_packages'),
            archivedPackages: (int) RowAccessor::string($row, 'archived_packages'),
            priceLists: (int) RowAccessor::string($row, 'price_lists'),
        );
    }

    /**
     * Tenant, status and free-text search — the three every family shares.
     *
     * @return array{where: list<string>, params: array<string, mixed>, types: array<string, ArrayParameterType>}
     */
    private function baseFilter(ClinicId $clinicId, CatalogListCriteria $criteria, string $alias): array
    {
        $where  = [\sprintf('%s.tenant_id = :clinicId', $alias)];
        $params = ['clinicId' => Uuid::fromString($clinicId->toString())->toBinary()];
        $types  = [];

        if (null !== $criteria->status) {
            $where[]          = \sprintf('%s.status = :status', $alias);
            $params['status'] = $criteria->status;
        }

        if (null !== $criteria->term && '' !== trim($criteria->term)) {
            $where[]        = \sprintf('(%s.name LIKE :term OR %s.code LIKE :term)', $alias, $alias);
            $params['term'] = '%' . trim($criteria->term) . '%';
        }

        if ([] !== $criteria->taxCategories) {
            $where[]                 = \sprintf('%s.tax_category_code IN (:taxCategories)', $alias);
            $params['taxCategories'] = $criteria->taxCategories;
            $types['taxCategories']  = ArrayParameterType::STRING;
        }

        return ['where' => $where, 'params' => $params, 'types' => $types];
    }

    /**
     * @param array<string, string> $sorts
     */
    private function order(array $sorts, CatalogListCriteria $criteria): string
    {
        $column = $sorts[$criteria->sortBy] ?? $sorts[CatalogListCriteria::SORT_NAME];

        return $column . ($criteria->sortDescending ? ' DESC' : ' ASC');
    }

    /**
     * @param array<string, mixed>              $params
     * @param array<string, ArrayParameterType> $types
     */
    private function count(string $from, string $where, array $params, array $types): int
    {
        $total = $this->connection->fetchOne(
            \sprintf('SELECT COUNT(*) FROM %s WHERE %s', $from, $where),
            $params,
            $types,
        );

        return is_numeric($total) ? (int) $total : 0;
    }
}
