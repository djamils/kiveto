<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Catalog\Application\Query;

use App\Context\Catalog\Application\Port\CatalogListReadRepositoryInterface;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\ActListRow;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\ArticleListRow;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\CatalogCounters;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\CatalogListCriteria;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\GetCatalogCounters;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\GetCatalogCountersHandler;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\ListActs;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\ListActsHandler;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\ListArticles;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\ListArticlesHandler;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\ListPackageComponents;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\ListPackageComponentsHandler;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\ListPackages;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\ListPackagesHandler;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\ListPriceListEntries;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\ListPriceListEntriesHandler;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\PackageComponentRow;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\PackageListRow;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\PriceListEntryRow;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use PHPUnit\Framework\TestCase;

final class CatalogListQueryHandlersTest extends TestCase
{
    private const string CLINIC_ID     = '01960000-0000-7000-8000-000000000901';
    private const string PACKAGE_ID    = '01960000-0000-7000-8000-000000000902';
    private const string PRICE_LIST_ID = '01960000-0000-7000-8000-000000000903';

    public function testTheActsPageCarriesItsPager(): void
    {
        $rows = [$this->act(), $this->act()];

        $catalog = $this->createMock(CatalogListReadRepositoryInterface::class);
        $catalog->expects(self::once())
            ->method('acts')
            ->with(self::callback(static fn (ClinicId $id): bool => self::CLINIC_ID === $id->toString()))
            ->willReturn(['items' => $rows, 'total' => 30])
        ;

        $page = (new ListActsHandler($catalog))(new ListActs(
            self::CLINIC_ID,
            new CatalogListCriteria(page: 2, perPage: 25),
        ));

        self::assertSame($rows, $page->items);
        self::assertSame(30, $page->total);
        self::assertSame(2, $page->pageCount());
        self::assertSame(26, $page->firstItem());
        self::assertSame(30, $page->lastItem());
    }

    public function testAnEmptyPageStillCountsAsOne(): void
    {
        $catalog = $this->createStub(CatalogListReadRepositoryInterface::class);
        $catalog->method('articles')->willReturn(['items' => [], 'total' => 0]);

        $page = (new ListArticlesHandler($catalog))(new ListArticles(
            self::CLINIC_ID,
            new CatalogListCriteria(),
        ));

        self::assertSame(1, $page->pageCount());
        self::assertSame(0, $page->firstItem());
        self::assertSame(0, $page->lastItem());
    }

    public function testThePackagesPageIsForwardedWithItsCriteria(): void
    {
        $criteria = new CatalogListCriteria(kinds: ['FIXED_PRICE']);
        $rows     = [$this->package()];

        $catalog = $this->createMock(CatalogListReadRepositoryInterface::class);
        $catalog->expects(self::once())
            ->method('packages')
            ->with(self::anything(), $criteria)
            ->willReturn(['items' => $rows, 'total' => 1])
        ;

        $page = (new ListPackagesHandler($catalog))(new ListPackages(self::CLINIC_ID, $criteria));

        self::assertSame($rows, $page->items);
        self::assertSame(1, $page->firstItem());
    }

    public function testTheComponentsOfAPackageAreForwarded(): void
    {
        $components = [
            new PackageComponentRow('c1', 'ACT', 'a1', 'CONS', 'Consultation', 4500, 'incl', 'EUR', 2, 0),
        ];

        $catalog = $this->createMock(CatalogListReadRepositoryInterface::class);
        $catalog->expects(self::once())
            ->method('packageComponents')
            ->with(self::anything(), self::PACKAGE_ID)
            ->willReturn($components)
        ;

        $result = (new ListPackageComponentsHandler($catalog))(
            new ListPackageComponents(self::CLINIC_ID, self::PACKAGE_ID),
        );

        self::assertSame($components, $result);
        self::assertSame(9000, $result[0]->lineTotalMinorUnits());
    }

    public function testTheEntriesOfAPriceListAreForwarded(): void
    {
        $entries = [
            new PriceListEntryRow('e1', 'ACT', 'a1', 'CONS', 'Consultation', 4500, 'incl', 3900, 'incl', 'EUR', null, 'tax.act'),
        ];

        $catalog = $this->createMock(CatalogListReadRepositoryInterface::class);
        $catalog->expects(self::once())
            ->method('priceListEntries')
            ->with(self::anything(), self::PRICE_LIST_ID)
            ->willReturn($entries)
        ;

        $result = (new ListPriceListEntriesHandler($catalog))(
            new ListPriceListEntries(self::CLINIC_ID, self::PRICE_LIST_ID),
        );

        self::assertSame($entries, $result);
        self::assertSame(-600, $result[0]->differenceMinorUnits());

        // Prices entered in different modes are not compared: Catalog never converts them.
        $mixed = new PriceListEntryRow('e2', 'ACT', 'a1', 'CONS', 'Consultation', 4500, 'excl', 3900, 'incl', 'EUR', null, 'tax.act');
        self::assertNull($mixed->differenceMinorUnits());
    }

    public function testTheCountersAreForwarded(): void
    {
        $counters = new CatalogCounters(3, 1, 5, 0, 2, 0, 1);

        $catalog = $this->createMock(CatalogListReadRepositoryInterface::class);
        $catalog->expects(self::once())->method('counters')->willReturn($counters);

        self::assertSame(
            $counters,
            (new GetCatalogCountersHandler($catalog))(new GetCatalogCounters(self::CLINIC_ID)),
        );
    }

    public function testTheCriteriaComputesItsOffset(): void
    {
        self::assertSame(0, (new CatalogListCriteria(page: 1, perPage: 25))->offset());
        self::assertSame(50, (new CatalogListCriteria(page: 3, perPage: 25))->offset());
        self::assertSame(0, (new CatalogListCriteria(page: 0, perPage: 25))->offset());
    }

    public function testARowKnowsWhetherItIsPutAway(): void
    {
        self::assertFalse($this->act()->isArchived());
        self::assertTrue($this->act(status: 'ARCHIVED')->isArchived());

        $drug = $this->article(kind: 'DRUG');
        self::assertTrue($drug->isDrug());
        self::assertFalse($drug->isArchived());

        self::assertFalse($this->article(kind: 'FOOD')->isDrug());
        self::assertTrue($this->article(status: 'ARCHIVED')->isArchived());
        self::assertTrue($this->package(status: 'ARCHIVED')->isArchived());
    }

    public function testAPackageIsChargedByItsMode(): void
    {
        $fixed = $this->package(mode: 'FIXED_PRICE', fixedPrice: 9900, componentsTotal: 12000);
        self::assertTrue($fixed->isFixedPrice());
        self::assertSame(9900, $fixed->effectivePriceMinorUnits());

        $sum = $this->package(mode: 'SUM_OF_COMPONENTS', fixedPrice: null, componentsTotal: 12000);
        self::assertFalse($sum->isFixedPrice());
        self::assertSame(12000, $sum->effectivePriceMinorUnits());

        // A fixed-price package whose price was somehow never set is free rather
        // than a fatal.
        $broken = $this->package(mode: 'FIXED_PRICE', fixedPrice: null, componentsTotal: 12000);
        self::assertSame(0, $broken->effectivePriceMinorUnits());
    }

    public function testADanglingReferenceIsFlaggedRatherThanPriced(): void
    {
        $component = new PackageComponentRow('c1', 'ACT', 'a1', null, null, null, null, 'EUR', 3, 0);
        self::assertTrue($component->isDangling());
        self::assertSame(0, $component->lineTotalMinorUnits());

        $entry = new PriceListEntryRow('e1', 'ACT', 'a1', null, null, null, null, 3900, 'incl', 'EUR', null, null);
        self::assertTrue($entry->isDangling());
        self::assertNull($entry->differenceMinorUnits());
    }

    private function act(string $status = 'ACTIVE'): ActListRow
    {
        return new ActListRow(
            id: 'a1',
            code: 'CONS_STD',
            name: 'Consultation standard',
            description: null,
            category: 'CONSULTATION',
            taxCategoryCode: 'veterinary.act.consultation',
            basePriceMinorUnits: 4500,
            currency: 'EUR',
            basePriceMode: 'incl',
            estimatedDurationMinutes: 20,
            requiresAnesthesia: false,
            status: $status,
        );
    }

    private function article(string $kind = 'DRUG', string $status = 'ACTIVE'): ArticleListRow
    {
        return new ArticleListRow(
            id: 'r1',
            code: 'AMOX-250',
            name: 'Amoxicilline 250',
            kind: $kind,
            gtin: null,
            taxCategoryCode: 'veterinary.drug.prescription',
            basePriceMinorUnits: 1250,
            currency: 'EUR',
            basePriceMode: 'incl',
            unitOfMeasure: 'UNIT',
            trackStock: true,
            authorizationRef: null,
            requiresPrescription: 'DRUG' === $kind,
            prescriptionClass: 'DRUG' === $kind ? 'RX' : null,
            isControlledSubstance: false,
            status: $status,
        );
    }

    private function package(
        string $mode = 'SUM_OF_COMPONENTS',
        ?int $fixedPrice = null,
        int $componentsTotal = 7000,
        string $status = 'ACTIVE',
    ): PackageListRow {
        return new PackageListRow(
            id: 'p1',
            code: 'PKG-VACC',
            name: 'Forfait vaccination',
            taxCategoryCode: 'veterinary.act.consultation',
            pricingMode: $mode,
            fixedPriceMinorUnits: $fixedPrice,
            fixedPriceMode: null !== $fixedPrice ? 'incl' : null,
            currency: 'EUR',
            componentCount: 2,
            componentsTotalMinorUnits: $componentsTotal,
            status: $status,
        );
    }
}
