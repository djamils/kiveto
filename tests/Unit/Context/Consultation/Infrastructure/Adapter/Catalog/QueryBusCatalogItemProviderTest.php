<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Infrastructure\Adapter\Catalog;

use App\Context\Catalog\Application\Query\Act\GetActDetail\ActDetailView;
use App\Context\Catalog\Application\Query\Act\GetActDetail\GetActDetail;
use App\Context\Catalog\Application\Query\Article\GetArticleDetail\ArticleDetailView;
use App\Context\Catalog\Application\Query\Article\GetArticleDetail\GetArticleDetail;
use App\Context\Catalog\Application\Query\Catalog\SearchCatalogItems\CatalogSearchResult;
use App\Context\Catalog\Application\Query\Catalog\SearchCatalogItems\SearchCatalogItems;
use App\Context\Catalog\Application\Query\Pricing\ResolvePrice\ResolvePrice;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListId;
use App\Context\Catalog\Domain\Pricing\ValueObject\ResolvedPrice;
use App\Context\Consultation\Application\Port\CatalogItemDto;
use App\Context\Consultation\Infrastructure\Adapter\Catalog\QueryBusCatalogItemProvider;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use App\System\PharmaceuticalRegistry\Application\Query\GetMarketingAuthorizationDetail\GetMarketingAuthorizationDetail;
use App\System\PharmaceuticalRegistry\Application\Query\GetMarketingAuthorizationDetail\MarketingAuthorizationDetailView;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class QueryBusCatalogItemProviderTest extends TestCase
{
    private const string CLINIC_ID     = '22222222-2222-4222-8222-222222222222';
    private const string ACT_ID        = '33333333-3333-4333-8333-333333333333';
    private const string ARTICLE_ID    = '44444444-4444-4444-8444-444444444444';
    private const string AUTH_ID       = '55555555-5555-4555-8555-555555555555';
    private const string PRICE_LIST_ID = '66666666-6666-4666-8666-666666666666';

    public function testSearchMapsCatalogResults(): void
    {
        $queryBus = $this->createStub(QueryBusInterface::class);
        $queryBus
            ->method('ask')
            ->willReturnCallback(function (object $query): array {
                self::assertInstanceOf(SearchCatalogItems::class, $query);
                self::assertSame(self::CLINIC_ID, $query->clinicId);
                self::assertSame('vacc', $query->term);
                self::assertSame(5, $query->limit);

                return [
                    new CatalogSearchResult(
                        id: self::ACT_ID,
                        type: 'ACT',
                        name: 'Consultation',
                        code: 'ACT-CONS',
                        status: 'ACTIVE',
                        basePriceMinorUnits: 3500,
                        basePriceCurrency: 'EUR',
                        taxCategoryCode: 'veterinary.act.consultation',
                        requiresPrescription: false,
                    ),
                    new CatalogSearchResult(
                        id: self::ARTICLE_ID,
                        type: 'ARTICLE',
                        name: 'Vaccin',
                        code: 'ART-VAC',
                        status: 'ARCHIVED',
                        basePriceMinorUnits: 1200,
                        basePriceCurrency: 'CHF',
                        taxCategoryCode: 'veterinary.medicine.oral',
                        requiresPrescription: true,
                    ),
                ];
            })
        ;

        $items = (new QueryBusCatalogItemProvider($queryBus))->search('vacc', self::CLINIC_ID, 5);

        self::assertCount(2, $items);
        self::assertSame('ACT', $items[0]->itemType);
        self::assertSame(self::ACT_ID, $items[0]->itemId);
        self::assertSame('Consultation', $items[0]->name);
        self::assertSame('ACT-CONS', $items[0]->code);
        self::assertFalse($items[0]->requiresPrescription);
        self::assertSame(3500, $items[0]->basePriceMinorUnits);
        self::assertSame('EUR', $items[0]->currency);
        self::assertSame('veterinary.act.consultation', $items[0]->taxCategoryCode);
        self::assertSame('ACTIVE', $items[0]->status);

        self::assertSame('ARTICLE', $items[1]->itemType);
        self::assertTrue($items[1]->requiresPrescription);
        self::assertSame('CHF', $items[1]->currency);
        self::assertSame('ARCHIVED', $items[1]->status);
    }

    public function testSearchReturnsNothingWhenTheBusDoesNotAnswerWithAnArray(): void
    {
        $queryBus = $this->createStub(QueryBusInterface::class);
        $queryBus->method('ask')->willReturn(null);

        self::assertSame([], (new QueryBusCatalogItemProvider($queryBus))->search('vacc', self::CLINIC_ID));
    }

    public function testSearchSkipsEntriesThatAreNotCatalogResults(): void
    {
        $queryBus = $this->createStub(QueryBusInterface::class);
        $queryBus->method('ask')->willReturn([
            'noise',
            new CatalogSearchResult(
                id: self::ACT_ID,
                type: 'ACT',
                name: 'Consultation',
                code: 'ACT-CONS',
                status: 'ACTIVE',
                basePriceMinorUnits: 3500,
                basePriceCurrency: 'EUR',
                taxCategoryCode: 'veterinary.act.consultation',
                requiresPrescription: false,
            ),
        ]);

        $items = (new QueryBusCatalogItemProvider($queryBus))->search('vacc', self::CLINIC_ID);

        self::assertCount(1, $items);
        self::assertSame(self::ACT_ID, $items[0]->itemId);
    }

    public function testDetailMapsAnAct(): void
    {
        $queryBus = $this->createStub(QueryBusInterface::class);
        $queryBus
            ->method('ask')
            ->willReturnCallback(function (object $query): ActDetailView {
                self::assertInstanceOf(GetActDetail::class, $query);
                self::assertSame(self::ACT_ID, $query->actId);
                self::assertSame(self::CLINIC_ID, $query->clinicId);

                return $this->actView();
            })
        ;

        $item = (new QueryBusCatalogItemProvider($queryBus))->detail('ACT', self::ACT_ID, self::CLINIC_ID);

        self::assertNotNull($item);
        self::assertSame('ACT', $item->itemType);
        self::assertSame(self::ACT_ID, $item->itemId);
        self::assertSame('Consultation', $item->name);
        self::assertSame('ACT-CONS', $item->code);
        // An act is never a prescription item.
        self::assertFalse($item->requiresPrescription);
        self::assertSame(3500, $item->basePriceMinorUnits);
        self::assertSame('EUR', $item->currency);
        self::assertSame('veterinary.act.consultation', $item->taxCategoryCode);
        self::assertSame('ACTIVE', $item->status);
    }

    public function testDetailReturnsNullWhenTheActLookupThrows(): void
    {
        $queryBus = $this->createStub(QueryBusInterface::class);
        $queryBus->method('ask')->willThrowException(new \RuntimeException('not found'));

        self::assertNull(
            (new QueryBusCatalogItemProvider($queryBus))->detail('ACT', self::ACT_ID, self::CLINIC_ID),
        );
    }

    public function testDetailReturnsNullWhenTheActLookupAnswersWithAnotherType(): void
    {
        $queryBus = $this->createStub(QueryBusInterface::class);
        $queryBus->method('ask')->willReturn('nope');

        self::assertNull(
            (new QueryBusCatalogItemProvider($queryBus))->detail('ACT', self::ACT_ID, self::CLINIC_ID),
        );
    }

    #[DataProvider('provideDetailMapsAnArticleCases')]
    public function testDetailMapsAnArticle(?bool $requiresPrescription, bool $expected): void
    {
        $queryBus = $this->createStub(QueryBusInterface::class);
        $queryBus
            ->method('ask')
            ->willReturnCallback(function (object $query) use ($requiresPrescription): ArticleDetailView {
                self::assertInstanceOf(GetArticleDetail::class, $query);
                self::assertSame(self::ARTICLE_ID, $query->articleId);
                self::assertSame(self::CLINIC_ID, $query->clinicId);

                return $this->articleView(requiresPrescription: $requiresPrescription);
            })
        ;

        $item = (new QueryBusCatalogItemProvider($queryBus))->detail('ARTICLE', self::ARTICLE_ID, self::CLINIC_ID);

        self::assertNotNull($item);
        self::assertSame('ARTICLE', $item->itemType);
        self::assertSame(self::ARTICLE_ID, $item->itemId);
        self::assertSame('Amoxicilline', $item->name);
        self::assertSame('ART-AMOX', $item->code);
        self::assertSame($expected, $item->requiresPrescription);
        self::assertSame(1200, $item->basePriceMinorUnits);
        self::assertSame('CHF', $item->currency);
        self::assertSame('veterinary.medicine.oral', $item->taxCategoryCode);
        self::assertSame('ACTIVE', $item->status);
    }

    /**
     * @return iterable<string, array{0: bool|null, 1: bool}>
     */
    public static function provideDetailMapsAnArticleCases(): iterable
    {
        yield 'explicitly required' => [true, true];
        yield 'explicitly not required' => [false, false];
        yield 'unknown' => [null, false];
    }

    public function testDetailFallsBackToTheArticleBranchForAnUnknownItemType(): void
    {
        $queryBus = $this->createStub(QueryBusInterface::class);
        $queryBus
            ->method('ask')
            ->willReturnCallback(function (object $query): ArticleDetailView {
                self::assertInstanceOf(GetArticleDetail::class, $query);

                return $this->articleView(requiresPrescription: true);
            })
        ;

        $item = (new QueryBusCatalogItemProvider($queryBus))->detail('SOMETHING', self::ARTICLE_ID, self::CLINIC_ID);

        self::assertNotNull($item);
        self::assertSame('ARTICLE', $item->itemType);
    }

    public function testDetailReturnsNullWhenTheArticleLookupThrows(): void
    {
        $queryBus = $this->createStub(QueryBusInterface::class);
        $queryBus->method('ask')->willThrowException(new \RuntimeException('not found'));

        self::assertNull(
            (new QueryBusCatalogItemProvider($queryBus))->detail('ARTICLE', self::ARTICLE_ID, self::CLINIC_ID),
        );
    }

    public function testDetailReturnsNullWhenTheArticleLookupAnswersWithAnotherType(): void
    {
        $queryBus = $this->createStub(QueryBusInterface::class);
        $queryBus->method('ask')->willReturn($this->actView());

        self::assertNull(
            (new QueryBusCatalogItemProvider($queryBus))->detail('ARTICLE', self::ARTICLE_ID, self::CLINIC_ID),
        );
    }

    public function testResolvePriceUsesTheResolvedNetAmount(): void
    {
        $queryBus = $this->createStub(QueryBusInterface::class);
        $queryBus
            ->method('ask')
            ->willReturnCallback(function (object $query): ResolvedPrice {
                self::assertInstanceOf(ResolvePrice::class, $query);
                self::assertSame(self::CLINIC_ID, $query->clinicId);
                self::assertNull($query->priceListId);
                self::assertSame('ACT', $query->itemType);
                self::assertSame(self::ACT_ID, $query->itemId);

                return $this->resolvedPrice(2900, 'CHF');
            })
        ;

        $price = (new QueryBusCatalogItemProvider($queryBus))->resolvePrice($this->item(), self::CLINIC_ID);

        self::assertSame(2900, $price->minorUnits);
        self::assertSame('CHF', $price->currency);
        // The tax category always comes from the catalog item, never from the price.
        self::assertSame('veterinary.act.consultation', $price->taxCategoryCode);
    }

    public function testResolvePriceFallsBackToTheBasePriceWhenTheBusThrows(): void
    {
        $queryBus = $this->createStub(QueryBusInterface::class);
        $queryBus->method('ask')->willThrowException(new \RuntimeException('no price list'));

        $price = (new QueryBusCatalogItemProvider($queryBus))->resolvePrice($this->item(), self::CLINIC_ID);

        self::assertSame(3500, $price->minorUnits);
        self::assertSame('EUR', $price->currency);
        self::assertSame('veterinary.act.consultation', $price->taxCategoryCode);
    }

    public function testResolvePriceFallsBackToTheBasePriceWhenTheBusAnswersWithAnotherType(): void
    {
        $queryBus = $this->createStub(QueryBusInterface::class);
        $queryBus->method('ask')->willReturn(2900);

        $price = (new QueryBusCatalogItemProvider($queryBus))->resolvePrice($this->item(), self::CLINIC_ID);

        self::assertSame(3500, $price->minorUnits);
        self::assertSame('EUR', $price->currency);
    }

    public function testActiveSubstancesReturnsTheAuthorizationLabels(): void
    {
        $queryBus = $this->createStub(QueryBusInterface::class);
        $queryBus->method('ask')->willReturnCallback(
            function (object $query): ArticleDetailView|MarketingAuthorizationDetailView {
                if ($query instanceof GetArticleDetail) {
                    self::assertSame(self::ARTICLE_ID, $query->articleId);

                    return $this->articleView(requiresPrescription: true, authorizationRef: self::AUTH_ID);
                }

                self::assertInstanceOf(GetMarketingAuthorizationDetail::class, $query);
                self::assertSame(self::AUTH_ID, $query->marketingAuthorizationId);

                return $this->authorizationView(['Amoxicilline', 'Acide clavulanique']);
            },
        );

        self::assertSame(
            ['Amoxicilline', 'Acide clavulanique'],
            (new QueryBusCatalogItemProvider($queryBus))->activeSubstances(self::ARTICLE_ID, self::CLINIC_ID),
        );
    }

    public function testActiveSubstancesSkipsEntriesThatAreNotUsableLabels(): void
    {
        $queryBus = $this->createStub(QueryBusInterface::class);
        $queryBus->method('ask')->willReturnCallback(
            function (object $query): ArticleDetailView|MarketingAuthorizationDetailView {
                if ($query instanceof GetArticleDetail) {
                    return $this->articleView(requiresPrescription: true, authorizationRef: self::AUTH_ID);
                }

                return $this->authorizationView([42, '', 'Amoxicilline', null]);
            },
        );

        self::assertSame(
            ['Amoxicilline'],
            (new QueryBusCatalogItemProvider($queryBus))->activeSubstances(self::ARTICLE_ID, self::CLINIC_ID),
        );
    }

    public function testActiveSubstancesReturnsNothingWhenTheArticleHasNoAuthorizationRef(): void
    {
        $queryBus = $this->createStub(QueryBusInterface::class);
        $queryBus->method('ask')->willReturn($this->articleView(requiresPrescription: true));

        self::assertSame(
            [],
            (new QueryBusCatalogItemProvider($queryBus))->activeSubstances(self::ARTICLE_ID, self::CLINIC_ID),
        );
    }

    public function testActiveSubstancesReturnsNothingWhenTheArticleLookupFails(): void
    {
        $queryBus = $this->createStub(QueryBusInterface::class);
        $queryBus->method('ask')->willThrowException(new \RuntimeException('not found'));

        self::assertSame(
            [],
            (new QueryBusCatalogItemProvider($queryBus))->activeSubstances(self::ARTICLE_ID, self::CLINIC_ID),
        );
    }

    public function testActiveSubstancesReturnsNothingWhenTheAuthorizationLookupAnswersWithAnotherType(): void
    {
        $queryBus = $this->createStub(QueryBusInterface::class);
        $queryBus->method('ask')->willReturnCallback(
            function (object $query): ArticleDetailView|string {
                if ($query instanceof GetArticleDetail) {
                    return $this->articleView(requiresPrescription: true, authorizationRef: self::AUTH_ID);
                }

                return 'unknown authorization';
            },
        );

        self::assertSame(
            [],
            (new QueryBusCatalogItemProvider($queryBus))->activeSubstances(self::ARTICLE_ID, self::CLINIC_ID),
        );
    }

    private function item(): CatalogItemDto
    {
        return new CatalogItemDto(
            itemType: 'ACT',
            itemId: self::ACT_ID,
            name: 'Consultation',
            code: 'ACT-CONS',
            requiresPrescription: false,
            basePriceMinorUnits: 3500,
            currency: 'EUR',
            taxCategoryCode: 'veterinary.act.consultation',
            status: 'ACTIVE',
        );
    }

    private function actView(): ActDetailView
    {
        return new ActDetailView(
            id: self::ACT_ID,
            clinicId: self::CLINIC_ID,
            name: 'Consultation',
            code: 'ACT-CONS',
            description: null,
            category: 'CONSULTATION',
            taxCategoryCode: 'veterinary.act.consultation',
            basePriceMinorUnits: 3500,
            basePriceCurrency: 'EUR',
            estimatedDurationMinutes: 30,
            requiresAnesthesia: false,
            status: 'ACTIVE',
            createdAt: '2026-04-10 09:00:00',
            updatedAt: '2026-04-10 09:00:00',
        );
    }

    private function articleView(?bool $requiresPrescription, ?string $authorizationRef = null): ArticleDetailView
    {
        return new ArticleDetailView(
            id: self::ARTICLE_ID,
            clinicId: self::CLINIC_ID,
            name: 'Amoxicilline',
            code: 'ART-AMOX',
            kind: 'MEDICATION',
            gtin: null,
            taxCategoryCode: 'veterinary.medicine.oral',
            basePriceMinorUnits: 1200,
            basePriceCurrency: 'CHF',
            unitOfMeasure: 'UNIT',
            trackStock: true,
            status: 'ACTIVE',
            authorizationRef: $authorizationRef,
            requiresPrescription: $requiresPrescription,
            prescriptionClass: null,
            isControlledSubstance: null,
            createdAt: '2026-04-10 09:00:00',
            updatedAt: '2026-04-10 09:00:00',
        );
    }

    /**
     * @param array<mixed> $activeSubstances
     */
    private function authorizationView(array $activeSubstances): MarketingAuthorizationDetailView
    {
        $at = new \DateTimeImmutable('2026-04-10 09:00:00');

        return new MarketingAuthorizationDetailView(
            id: self::AUTH_ID,
            commercialName: 'Amoxival',
            holderLaboratory: 'Labo',
            status: 'ACTIVE',
            authorizationDate: '2020-01-01',
            nature: 'MEDICINE',
            pharmaceuticalForm: 'TABLET',
            atcVetCode: null,
            permanentIdentifier: null,
            controlledSubstanceClass: null,
            presentations: [],
            activeSubstances: $activeSubstances,
            targetUsages: [],
            jurisdictionalIdentifiers: [],
            lastImportSource: 'TEST',
            lastImportedAt: $at,
            createdAt: $at,
            updatedAt: $at,
        );
    }

    private function resolvedPrice(int $minorUnits, string $currency): ResolvedPrice
    {
        $amount = Money::fromMinorUnits($minorUnits, CurrencyCode::fromString($currency));

        return new ResolvedPrice(
            netAmount: $amount,
            baseAmount: $amount,
            appliedRules: [],
            sourcePriceList: PriceListId::fromString(self::PRICE_LIST_ID),
            sourcePriceListItem: null,
            resolvedAt: new \DateTimeImmutable('2026-04-10 09:00:00'),
        );
    }
}
