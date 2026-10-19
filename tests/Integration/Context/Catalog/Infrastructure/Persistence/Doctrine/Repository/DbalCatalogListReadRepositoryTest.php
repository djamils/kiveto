<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Catalog\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Catalog\Application\Port\CatalogListReadRepositoryInterface;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\CatalogListCriteria;
use App\Context\Catalog\Domain\Act\ValueObject\ActId;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleId;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleKind;
use App\Context\Catalog\Domain\Package\Package;
use App\Context\Catalog\Domain\Package\Repository\PackageRepositoryInterface;
use App\Context\Catalog\Domain\Package\ValueObject\PackageCode;
use App\Context\Catalog\Domain\Package\ValueObject\PackageId;
use App\Context\Catalog\Domain\Package\ValueObject\PackageName;
use App\Context\Catalog\Domain\Package\ValueObject\PackagePricingMode;
use App\Context\Catalog\Domain\Package\ValueObject\Quantity;
use App\Context\Catalog\Domain\Pricing\PriceList;
use App\Context\Catalog\Domain\Pricing\PriceListItem;
use App\Context\Catalog\Domain\Pricing\Repository\PriceListRepositoryInterface;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListId;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListItemId;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListName;
use App\Context\Catalog\Domain\Shared\ValueObject\CatalogItemRef;
use App\Context\Catalog\Domain\ValueObject\CatalogPrice;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Fixtures\Context\Catalog\Factory\ActEntityFactory;
use App\Fixtures\Context\Catalog\Factory\ArticleEntityFactory;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

/**
 * The listing side of the catalogue against a real database: the filters have to
 * narrow, the sorts have to order, and a package or a price list has to show the
 * names of what it points at.
 */
final class DbalCatalogListReadRepositoryTest extends KernelTestCase
{
    use Factories;

    private CatalogListReadRepositoryInterface $catalog;
    private string $clinicId;
    private string $otherClinicId;

    protected function setUp(): void
    {
        parent::setUp();

        $catalog = self::getContainer()->get(CatalogListReadRepositoryInterface::class);
        \assert($catalog instanceof CatalogListReadRepositoryInterface);
        $this->catalog = $catalog;

        $this->clinicId      = Uuid::v7()->toRfc4122();
        $this->otherClinicId = Uuid::v7()->toRfc4122();
    }

    // ── Acts ──

    public function testTheActsOfTheClinicAreListedAndCounted(): void
    {
        $this->act('CONS-A', 'Consultation standard', 'CONSULTATION', 4500);
        $this->act('CHIR-A', 'Castration', 'SURGERY', 18000);
        ActEntityFactory::new()->forClinic($this->otherClinicId)->create(['code' => 'CONS-B', 'name' => 'Ailleurs']);

        $result = $this->catalog->acts($this->clinic(), new CatalogListCriteria());

        self::assertSame(2, $result['total']);
        self::assertSame(['Castration', 'Consultation standard'], array_map(
            static fn ($row): string => $row->name,
            $result['items'],
        ));
    }

    public function testTheActsAreNarrowedByCategoryStatusAndTerm(): void
    {
        $this->act('CONS-A', 'Consultation standard', 'CONSULTATION', 4500);
        $this->act('CONS-B', 'Consultation de suivi', 'CONSULTATION', 3500);
        $this->act('CHIR-A', 'Castration', 'SURGERY', 18000);
        $this->act('OLD-A', 'Consultation ancienne', 'CONSULTATION', 3000, archived: true);

        $byCategory = $this->catalog->acts($this->clinic(), new CatalogListCriteria(kinds: ['SURGERY']));
        self::assertSame(1, $byCategory['total']);
        self::assertSame('Castration', $byCategory['items'][0]->name);

        $archived = $this->catalog->acts($this->clinic(), new CatalogListCriteria(
            status: CatalogListCriteria::STATUS_ARCHIVED,
        ));
        self::assertSame(1, $archived['total']);
        self::assertTrue($archived['items'][0]->isArchived());

        $byTerm = $this->catalog->acts($this->clinic(), new CatalogListCriteria(term: 'suivi'));
        self::assertSame(1, $byTerm['total']);

        $byCode = $this->catalog->acts($this->clinic(), new CatalogListCriteria(term: 'CHIR'));
        self::assertSame(1, $byCode['total']);

        $everything = $this->catalog->acts($this->clinic(), new CatalogListCriteria(status: null));
        self::assertSame(4, $everything['total']);
    }

    public function testTheActsAreSortedAndPaginated(): void
    {
        $this->act('A-1', 'Alpha', 'CONSULTATION', 1000);
        $this->act('B-2', 'Bravo', 'CONSULTATION', 3000);
        $this->act('C-3', 'Charlie', 'CONSULTATION', 2000);

        $byPrice = $this->catalog->acts($this->clinic(), new CatalogListCriteria(
            sortBy: CatalogListCriteria::SORT_PRICE,
            sortDescending: true,
        ));

        self::assertSame([3000, 2000, 1000], array_map(
            static fn ($row): int => $row->basePriceMinorUnits,
            $byPrice['items'],
        ));

        $secondPage = $this->catalog->acts($this->clinic(), new CatalogListCriteria(page: 2, perPage: 2));

        self::assertSame(3, $secondPage['total']);
        self::assertCount(1, $secondPage['items']);
        self::assertSame('Charlie', $secondPage['items'][0]->name);
    }

    public function testAnActKeepsEverythingThePageShows(): void
    {
        $this->act('CHIR-A', 'Castration', 'SURGERY', 18000, anesthesia: true, duration: 45);

        $row = $this->catalog->acts($this->clinic(), new CatalogListCriteria())['items'][0];

        self::assertSame('CHIR-A', $row->code);
        self::assertSame('SURGERY', $row->category);
        self::assertSame(18000, $row->basePriceMinorUnits);
        self::assertSame('EUR', $row->currency);
        self::assertSame('incl', $row->basePriceMode);
        self::assertSame(45, $row->estimatedDurationMinutes);
        self::assertTrue($row->requiresAnesthesia);
        self::assertFalse($row->isArchived());
    }

    public function testAnEmptyCatalogListsNothing(): void
    {
        $result = $this->catalog->acts($this->clinic(), new CatalogListCriteria());

        self::assertSame(['items' => [], 'total' => 0], $result);
    }

    // ── Articles ──

    public function testTheArticlesAreNarrowedByKindAndPrescription(): void
    {
        $this->drug('RX-A', 'Amoxicilline 250', controlled: false);
        $this->drug('RX-B', 'Morphine', controlled: true);
        $this->nonDrug('CONS-A', 'Compresses', 'CONSUMABLE');
        $this->nonDrug('FOOD-A', 'Croquettes rénales', 'FOOD');

        $drugs = $this->catalog->articles($this->clinic(), new CatalogListCriteria(kinds: ['DRUG']));
        self::assertSame(2, $drugs['total']);
        self::assertSame('incl', $drugs['items'][0]->basePriceMode);

        $food = $this->catalog->articles($this->clinic(), new CatalogListCriteria(kinds: ['FOOD', 'CONSUMABLE']));
        self::assertSame(2, $food['total']);

        $onPrescription = $this->catalog->articles($this->clinic(), new CatalogListCriteria(
            requiresPrescription: true,
        ));
        self::assertSame(2, $onPrescription['total']);

        $overTheCounter = $this->catalog->articles($this->clinic(), new CatalogListCriteria(
            requiresPrescription: false,
        ));
        self::assertSame(2, $overTheCounter['total']);

        $controlled = $this->catalog->articles($this->clinic(), new CatalogListCriteria(controlledOnly: true));
        self::assertSame(1, $controlled['total']);
        self::assertSame('Morphine', $controlled['items'][0]->name);
    }

    public function testADrugKeepsItsPharmaceuticalFlags(): void
    {
        $this->drug('RX-B', 'Morphine', controlled: true);

        $row = $this->catalog->articles($this->clinic(), new CatalogListCriteria())['items'][0];

        self::assertTrue($row->isDrug());
        self::assertTrue($row->requiresPrescription);
        self::assertTrue($row->isControlledSubstance);
        self::assertSame('RX_CONTROLLED', $row->prescriptionClass);
    }

    public function testANonDrugCarriesNoPrescriptionFlag(): void
    {
        $this->nonDrug('CONS-A', 'Compresses', 'CONSUMABLE');

        $row = $this->catalog->articles($this->clinic(), new CatalogListCriteria())['items'][0];

        self::assertFalse($row->isDrug());
        self::assertFalse($row->requiresPrescription);
        self::assertFalse($row->isControlledSubstance);
        self::assertNull($row->prescriptionClass);
    }

    public function testAnEmptyArticleCatalogListsNothing(): void
    {
        self::assertSame(
            ['items' => [], 'total' => 0],
            $this->catalog->articles($this->clinic(), new CatalogListCriteria()),
        );
    }

    // ── Packages ──

    public function testAPackageShowsWhatItHoldsAndWhatItWouldCost(): void
    {
        $act     = $this->act('CONS-A', 'Consultation standard', 'CONSULTATION', 4500);
        $article = $this->drug('RX-A', 'Amoxicilline 250', controlled: false, price: 1250);

        $package = $this->package('PKG-A', 'Forfait vaccination', PackagePricingMode::SUM_OF_COMPONENTS);
        $package->addComponent(CatalogItemRef::toAct(ActId::fromString($act)), Quantity::of(1), 0, $this->now());
        $package->addComponent(
            CatalogItemRef::toArticle(ArticleId::fromString($article)),
            Quantity::of(2),
            1,
            $this->now(),
        );
        $this->packages()->save($package);

        $row = $this->catalog->packages($this->clinic(), new CatalogListCriteria())['items'][0];

        self::assertSame('PKG-A', $row->code);
        self::assertSame(2, $row->componentCount);
        // 1 × 45,00 € + 2 × 12,50 €
        self::assertSame(7000, $row->componentsTotalMinorUnits);
        self::assertSame(7000, $row->effectivePriceMinorUnits());
        self::assertFalse($row->isFixedPrice());

        $components = $this->catalog->packageComponents($this->clinic(), $package->id()->toString());

        self::assertCount(2, $components);
        self::assertSame('Consultation standard', $components[0]->itemName);
        self::assertSame('CONS-A', $components[0]->itemCode);
        self::assertSame(4500, $components[0]->lineTotalMinorUnits());
        self::assertSame('incl', $components[0]->unitPriceMode);
        self::assertFalse($components[0]->isDangling());
        self::assertSame('Amoxicilline 250', $components[1]->itemName);
        self::assertSame(2500, $components[1]->lineTotalMinorUnits());
    }

    public function testAFixedPricePackageIsChargedAtItsFixedPrice(): void
    {
        $act     = $this->act('CONS-A', 'Consultation standard', 'CONSULTATION', 4500);
        $package = $this->package('PKG-B', 'Forfait chirurgie', PackagePricingMode::FIXED_PRICE, 9900);
        $package->addComponent(CatalogItemRef::toAct(ActId::fromString($act)), Quantity::of(1), 0, $this->now());
        $this->packages()->save($package);

        $row = $this->catalog->packages($this->clinic(), new CatalogListCriteria())['items'][0];

        self::assertTrue($row->isFixedPrice());
        self::assertSame(9900, $row->effectivePriceMinorUnits());
        self::assertSame('incl', $row->fixedPriceMode);
        self::assertSame(4500, $row->componentsTotalMinorUnits);
    }

    public function testAComponentWhoseItemIsGoneIsStillListed(): void
    {
        $package = $this->package('PKG-C', 'Forfait fantôme', PackagePricingMode::SUM_OF_COMPONENTS);
        $package->addComponent(
            CatalogItemRef::toAct(ActId::fromString(Uuid::v7()->toRfc4122())),
            Quantity::of(1),
            0,
            $this->now(),
        );
        $this->packages()->save($package);

        $components = $this->catalog->packageComponents($this->clinic(), $package->id()->toString());

        self::assertCount(1, $components);
        self::assertTrue($components[0]->isDangling());
        self::assertSame(0, $components[0]->lineTotalMinorUnits());
    }

    public function testAClinicWithoutForfaitsListsNothing(): void
    {
        self::assertSame(
            ['items' => [], 'total' => 0],
            $this->catalog->packages($this->clinic(), new CatalogListCriteria()),
        );
    }

    public function testThePackagesAreNarrowedByTheirPricingMode(): void
    {
        $this->packages()->save($this->package('PKG-A', 'Au forfait', PackagePricingMode::FIXED_PRICE, 9900));
        $this->packages()->save($this->package('PKG-B', 'À la somme', PackagePricingMode::SUM_OF_COMPONENTS));

        $fixed = $this->catalog->packages($this->clinic(), new CatalogListCriteria(kinds: ['FIXED_PRICE']));

        self::assertSame(1, $fixed['total']);
        self::assertSame('PKG-A', $fixed['items'][0]->code);
        self::assertSame('EUR', $fixed['items'][0]->currency);

        // A package priced at the sum of nothing carries no currency of its own.
        $sum = $this->catalog->packages($this->clinic(), new CatalogListCriteria(kinds: ['SUM_OF_COMPONENTS']));
        self::assertSame('EUR', $sum['items'][0]->currency);
        self::assertNull($sum['items'][0]->fixedPriceMode);
        self::assertSame(0, $sum['items'][0]->componentsTotalMinorUnits);
    }

    public function testAnOverrideThatIsRemovedLeavesTheGridBehindIt(): void
    {
        $act = $this->act('CONS-A', 'Consultation standard', 'CONSULTATION', 4500);

        $priceList = PriceList::create(
            PriceListId::fromString(Uuid::v7()->toRfc4122()),
            $this->clinic(),
            PriceListName::fromString('Tarifs'),
            false,
            $this->now(),
        );
        $override = $this->override($act, 3900);
        $priceList->addItem($override, $this->now());
        $this->priceLists()->save($priceList);

        $reloaded = $this->priceLists()->findById($priceList->id(), $this->clinic());
        self::assertNotNull($reloaded);
        $reloaded->removeItem($override->id(), $this->now());
        $this->priceLists()->save($reloaded);

        self::assertSame([], $this->catalog->priceListEntries($this->clinic(), $priceList->id()->toString()));
    }

    public function testThePackagesOfAnotherClinicAreInvisible(): void
    {
        $package = $this->package('PKG-A', 'Forfait', PackagePricingMode::SUM_OF_COMPONENTS);
        $this->packages()->save($package);

        $result = $this->catalog->packages(ClinicId::fromString($this->otherClinicId), new CatalogListCriteria());

        self::assertSame(0, $result['total']);
        self::assertSame([], $this->catalog->packageComponents(
            ClinicId::fromString($this->otherClinicId),
            $package->id()->toString(),
        ));
    }

    // ── Price lists ──

    public function testAnOverrideIsResolvedAgainstTheItemItAppliesTo(): void
    {
        $act = $this->act('CONS-A', 'Consultation standard', 'CONSULTATION', 4500);

        $priceList = PriceList::create(
            PriceListId::fromString(Uuid::v7()->toRfc4122()),
            $this->clinic(),
            PriceListName::fromString('Tarifs partenaires'),
            false,
            $this->now(),
        );
        $priceList->addItem(
            PriceListItem::create(
                PriceListItemId::fromString(Uuid::v7()->toRfc4122()),
                CatalogItemRef::toAct(ActId::fromString($act)),
                CatalogPrice::includingTax(Money::fromMinorUnits(3900, CurrencyCode::fromString('EUR'))),
                TaxCategoryCode::fromString('veterinary.act.consultation'),
            ),
            $this->now(),
        );
        $this->priceLists()->save($priceList);

        $entries = $this->catalog->priceListEntries($this->clinic(), $priceList->id()->toString());

        self::assertCount(1, $entries);
        self::assertSame('Consultation standard', $entries[0]->itemName);
        self::assertSame(4500, $entries[0]->basePriceMinorUnits);
        self::assertSame(3900, $entries[0]->priceMinorUnits);
        self::assertSame('incl', $entries[0]->priceMode);
        self::assertSame('incl', $entries[0]->basePriceMode);
        self::assertSame(-600, $entries[0]->differenceMinorUnits());
        self::assertFalse($entries[0]->isDangling());
    }

    public function testAnOverrideOnAVanishedItemIsFlaggedRatherThanHidden(): void
    {
        $priceList = PriceList::create(
            PriceListId::fromString(Uuid::v7()->toRfc4122()),
            $this->clinic(),
            PriceListName::fromString('Tarifs'),
            false,
            $this->now(),
        );
        $priceList->addItem(
            PriceListItem::create(
                PriceListItemId::fromString(Uuid::v7()->toRfc4122()),
                CatalogItemRef::toArticle(ArticleId::fromString(Uuid::v7()->toRfc4122())),
                CatalogPrice::includingTax(Money::fromMinorUnits(1000, CurrencyCode::fromString('EUR'))),
                null,
            ),
            $this->now(),
        );
        $this->priceLists()->save($priceList);

        $entries = $this->catalog->priceListEntries($this->clinic(), $priceList->id()->toString());

        self::assertCount(1, $entries);
        self::assertTrue($entries[0]->isDangling());
        self::assertNull($entries[0]->differenceMinorUnits());
    }

    public function testAGridSavedTwiceKeepsItsOverridesRatherThanColliding(): void
    {
        $first  = $this->act('CONS-A', 'Consultation standard', 'CONSULTATION', 4500);
        $second = $this->act('CHIR-A', 'Castration', 'SURGERY', 18000);

        $priceList = PriceList::create(
            PriceListId::fromString(Uuid::v7()->toRfc4122()),
            $this->clinic(),
            PriceListName::fromString('Tarifs partenaires'),
            false,
            $this->now(),
        );
        $priceList->addItem($this->override($first, 3900), $this->now());
        $this->priceLists()->save($priceList);

        // A second write on the same aggregate: the first override is already in
        // the identity map, and re-inserting it under its own id used to blow up.
        $reloaded = $this->priceLists()->findById($priceList->id(), $this->clinic());
        self::assertNotNull($reloaded);
        $reloaded->addItem($this->override($second, 16000), $this->now());
        $this->priceLists()->save($reloaded);

        $entries = $this->catalog->priceListEntries($this->clinic(), $priceList->id()->toString());

        self::assertCount(2, $entries);
        self::assertSame(
            [16000, 3900],
            array_map(static fn ($entry): int => $entry->priceMinorUnits, $entries),
        );
    }

    public function testThePriceListOfAnotherClinicIsInvisible(): void
    {
        $priceList = PriceList::create(
            PriceListId::fromString(Uuid::v7()->toRfc4122()),
            $this->clinic(),
            PriceListName::fromString('Tarifs'),
            false,
            $this->now(),
        );
        $this->priceLists()->save($priceList);

        self::assertSame([], $this->catalog->priceListEntries(
            ClinicId::fromString($this->otherClinicId),
            $priceList->id()->toString(),
        ));
    }

    // ── Counters ──

    public function testTheCountersSplitWhatIsOnOfferFromWhatIsPutAway(): void
    {
        $this->act('CONS-A', 'Consultation', 'CONSULTATION', 4500);
        $this->act('OLD-A', 'Ancienne', 'CONSULTATION', 3000, archived: true);
        $this->drug('RX-A', 'Amoxicilline', controlled: false);
        $this->nonDrug('CONS-B', 'Compresses', 'CONSUMABLE');

        $this->packages()->save($this->package('PKG-A', 'Forfait', PackagePricingMode::SUM_OF_COMPONENTS));

        $priceList = PriceList::create(
            PriceListId::fromString(Uuid::v7()->toRfc4122()),
            $this->clinic(),
            PriceListName::fromString('Tarifs'),
            true,
            $this->now(),
        );
        $this->priceLists()->save($priceList);

        $counters = $this->catalog->counters($this->clinic());

        self::assertSame(1, $counters->activeActs);
        self::assertSame(1, $counters->archivedActs);
        self::assertSame(2, $counters->activeArticles);
        self::assertSame(0, $counters->archivedArticles);
        self::assertSame(1, $counters->activePackages);
        self::assertSame(0, $counters->archivedPackages);
        self::assertSame(1, $counters->priceLists);
    }

    public function testAClinicWithNothingCountsZero(): void
    {
        $counters = $this->catalog->counters($this->clinic());

        self::assertSame(0, $counters->activeActs);
        self::assertSame(0, $counters->activeArticles);
        self::assertSame(0, $counters->activePackages);
        self::assertSame(0, $counters->priceLists);
    }

    private function override(string $actId, int $netPrice): PriceListItem
    {
        return PriceListItem::create(
            PriceListItemId::fromString(Uuid::v7()->toRfc4122()),
            CatalogItemRef::toAct(ActId::fromString($actId)),
            CatalogPrice::includingTax(Money::fromMinorUnits($netPrice, CurrencyCode::fromString('EUR'))),
            null,
        );
    }

    private function act(
        string $code,
        string $name,
        string $category,
        int $price,
        bool $archived = false,
        bool $anesthesia = false,
        int $duration = 20,
    ): string {
        $id      = Uuid::v7();
        $factory = ActEntityFactory::new()->forClinic($this->clinicId);

        if ($archived) {
            $factory = $factory->archived();
        }

        $factory->create([
            'id'                       => $id,
            'code'                     => $code,
            'name'                     => $name,
            'category'                 => $category,
            'basePriceMinorUnits'      => $price,
            'basePriceCurrency'        => 'EUR',
            'estimatedDurationMinutes' => $duration,
            'requiresAnesthesia'       => $anesthesia,
        ]);

        return $id->toRfc4122();
    }

    private function drug(string $code, string $name, bool $controlled, int $price = 1250): string
    {
        $id = Uuid::v7();

        ArticleEntityFactory::new()->drug()->forClinic($this->clinicId)->create([
            'id'                    => $id,
            'code'                  => $code,
            'name'                  => $name,
            'basePriceMinorUnits'   => $price,
            'basePriceCurrency'     => 'EUR',
            'drugRequiresRx'        => true,
            'drugPrescriptionClass' => $controlled ? 'RX_CONTROLLED' : 'RX',
            'drugIsControlled'      => $controlled,
        ]);

        return $id->toRfc4122();
    }

    private function nonDrug(string $code, string $name, string $kind): string
    {
        $id = Uuid::v7();

        ArticleEntityFactory::new()->forClinic($this->clinicId)->create([
            'id'                  => $id,
            'code'                => $code,
            'name'                => $name,
            'kind'                => ArticleKind::from($kind),
            'basePriceMinorUnits' => 350,
            'basePriceCurrency'   => 'EUR',
        ]);

        return $id->toRfc4122();
    }

    private function package(
        string $code,
        string $name,
        PackagePricingMode $mode,
        ?int $fixedPrice = null,
    ): Package {
        return Package::create(
            PackageId::fromString(Uuid::v7()->toRfc4122()),
            $this->clinic(),
            PackageName::fromString($name),
            PackageCode::fromString($code),
            TaxCategoryCode::fromString('veterinary.act.consultation'),
            $mode,
            null !== $fixedPrice
                ? CatalogPrice::includingTax(Money::fromMinorUnits($fixedPrice, CurrencyCode::fromString('EUR')))
                : null,
            $this->now(),
        );
    }

    private function packages(): PackageRepositoryInterface
    {
        $repository = self::getContainer()->get(PackageRepositoryInterface::class);
        \assert($repository instanceof PackageRepositoryInterface);

        return $repository;
    }

    private function priceLists(): PriceListRepositoryInterface
    {
        $repository = self::getContainer()->get(PriceListRepositoryInterface::class);
        \assert($repository instanceof PriceListRepositoryInterface);

        return $repository;
    }

    private function clinic(): ClinicId
    {
        return ClinicId::fromString($this->clinicId);
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-08-22 10:00:00');
    }
}
