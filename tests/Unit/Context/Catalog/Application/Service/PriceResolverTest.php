<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Catalog\Application\Service;

use App\Context\Catalog\Application\Service\PriceResolver;
use App\Context\Catalog\Domain\Act\Act;
use App\Context\Catalog\Domain\Act\Repository\ActRepositoryInterface;
use App\Context\Catalog\Domain\Act\ValueObject\ActCategory;
use App\Context\Catalog\Domain\Act\ValueObject\ActCode;
use App\Context\Catalog\Domain\Act\ValueObject\ActDuration;
use App\Context\Catalog\Domain\Act\ValueObject\ActId;
use App\Context\Catalog\Domain\Act\ValueObject\ActName;
use App\Context\Catalog\Domain\Article\Repository\ArticleRepositoryInterface;
use App\Context\Catalog\Domain\Pricing\Exception\NoPriceFoundForItemException;
use App\Context\Catalog\Domain\Pricing\Exception\PriceListNotFoundException;
use App\Context\Catalog\Domain\Pricing\PriceList;
use App\Context\Catalog\Domain\Pricing\PriceListItem;
use App\Context\Catalog\Domain\Pricing\Repository\PriceListRepositoryInterface;
use App\Context\Catalog\Domain\Pricing\Service\PriceCalculator;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListId;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListItemId;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListName;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListStatus;
use App\Context\Catalog\Domain\Pricing\ValueObject\PricingContext;
use App\Context\Catalog\Domain\Shared\ValueObject\CatalogItemRef;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\Service\CurrencyRegistry;
use App\Shared\Money\Domain\Service\MoneyCalculator;
use App\Shared\Money\Domain\Service\RoundingPolicyRegistry;
use App\Shared\Money\Domain\ValueObject\Currency;
use App\Shared\Money\Domain\ValueObject\CurrencyDecimals;
use App\Shared\Money\Domain\ValueObject\CurrencySymbol;
use App\Shared\Money\Domain\ValueObject\Money;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

final class PriceResolverTest extends TestCase
{
    private const string CLINIC_ID     = '01950000-0000-7000-0000-0000000000c1';
    private const string PRICE_LIST_ID = '01950000-0000-7000-0000-0000000000a2';
    private const string ITEM_ID       = '01950000-0000-7000-0000-0000000000b3';
    private const string ACT_ID        = '01950000-0000-7000-0000-0000000000d4';

    private PriceListRepositoryInterface&Stub $priceListRepository;
    private ActRepositoryInterface&Stub $actRepository;
    private ArticleRepositoryInterface&Stub $articleRepository;
    private ClockInterface&Stub $clock;

    protected function setUp(): void
    {
        $this->priceListRepository = $this->createStub(PriceListRepositoryInterface::class);
        $this->actRepository       = $this->createStub(ActRepositoryInterface::class);
        $this->articleRepository   = $this->createStub(ArticleRepositoryInterface::class);
        $this->clock               = $this->createStub(ClockInterface::class);

        $this->clock->method('now')->willReturn(new \DateTimeImmutable('2026-08-20T10:00:00+00:00'));
    }

    public function testResolvesRequestedNonDefaultListWithItemOverride(): void
    {
        $priceList           = $this->makePriceList(isDefault: false, withItem: true);
        $priceListRepository = $this->mockPriceListRepository();

        $priceListRepository
            ->expects(self::once())
            ->method('findById')
            ->with(
                self::callback(static fn (PriceListId $id) => self::PRICE_LIST_ID === $id->toString()),
                self::callback(static fn (ClinicId $id) => self::CLINIC_ID === $id->toString()),
            )
            ->willReturn($priceList)
        ;
        $priceListRepository->expects(self::never())->method('findDefaultForClinic');

        $resolved = $this->makeResolver()->resolve($this->actRef(), $this->makeContext(self::PRICE_LIST_ID));

        self::assertSame(1480, $resolved->netAmount->minorUnits());
        self::assertSame(self::PRICE_LIST_ID, $resolved->sourcePriceList->toString());
        self::assertNotNull($resolved->sourcePriceListItem);
        self::assertSame(self::ITEM_ID, $resolved->sourcePriceListItem->toString());
    }

    public function testFallsBackToDefaultListWhenNoListRequested(): void
    {
        $priceList           = $this->makePriceList(isDefault: true, withItem: true);
        $priceListRepository = $this->mockPriceListRepository();

        $priceListRepository
            ->expects(self::once())
            ->method('findDefaultForClinic')
            ->with(self::callback(static fn (ClinicId $id) => self::CLINIC_ID === $id->toString()))
            ->willReturn($priceList)
        ;
        $priceListRepository->expects(self::never())->method('findById');

        $resolved = $this->makeResolver()->resolve($this->actRef(), $this->makeContext(null));

        self::assertSame(1480, $resolved->netAmount->minorUnits());
        self::assertSame(self::PRICE_LIST_ID, $resolved->sourcePriceList->toString());
    }

    public function testFallsBackToAggregateBasePriceWhenItemAbsentFromList(): void
    {
        $priceList = $this->makePriceList(isDefault: false, withItem: false);

        $this->priceListRepository->method('findById')->willReturn($priceList);

        $actRepository       = $this->createMock(ActRepositoryInterface::class);
        $this->actRepository = $actRepository;
        $actRepository
            ->expects(self::once())
            ->method('findById')
            ->with(
                self::callback(static fn (ActId $id) => self::ACT_ID === $id->toString()),
                self::callback(static fn (ClinicId $id) => self::CLINIC_ID === $id->toString()),
            )
            ->willReturn($this->makeAct())
        ;

        $resolved = $this->makeResolver()->resolve($this->actRef(), $this->makeContext(self::PRICE_LIST_ID));

        self::assertSame(5000, $resolved->netAmount->minorUnits());
        self::assertNull($resolved->sourcePriceListItem);
    }

    public function testThrowsWhenRequestedListNotFound(): void
    {
        $this->priceListRepository->method('findById')->willReturn(null);

        $this->expectException(PriceListNotFoundException::class);
        $this->expectExceptionMessage(\sprintf('Price list "%s" not found.', self::PRICE_LIST_ID));

        $this->makeResolver()->resolve($this->actRef(), $this->makeContext(self::PRICE_LIST_ID));
    }

    public function testThrowsWhenDefaultListNotFound(): void
    {
        $this->priceListRepository->method('findDefaultForClinic')->willReturn(null);

        $this->expectException(PriceListNotFoundException::class);
        $this->expectExceptionMessage(\sprintf('Price list "default (clinic %s)" not found.', self::CLINIC_ID));

        $this->makeResolver()->resolve($this->actRef(), $this->makeContext(null));
    }

    public function testThrowsWhenNoPriceFoundAnywhere(): void
    {
        $priceList = $this->makePriceList(isDefault: false, withItem: false);

        $this->priceListRepository->method('findById')->willReturn($priceList);
        $this->actRepository->method('findById')->willReturn(null);

        $this->expectException(NoPriceFoundForItemException::class);

        $this->makeResolver()->resolve($this->actRef(), $this->makeContext(self::PRICE_LIST_ID));
    }

    private function mockPriceListRepository(): PriceListRepositoryInterface&MockObject
    {
        $mock                      = $this->createMock(PriceListRepositoryInterface::class);
        $this->priceListRepository = $mock;

        return $mock;
    }

    private function makeResolver(): PriceResolver
    {
        $eur = Currency::of(
            code: CurrencyCode::fromString('EUR'),
            symbol: CurrencySymbol::fromString('€'),
            decimals: CurrencyDecimals::of(2),
            displayName: 'Euro',
        );

        $currencyRegistry = $this->createStub(CurrencyRegistry::class);
        $currencyRegistry->method('get')->willReturn($eur);

        return new PriceResolver(
            priceListRepository: $this->priceListRepository,
            actRepository: $this->actRepository,
            articleRepository: $this->articleRepository,
            priceCalculator: new PriceCalculator(new MoneyCalculator($currencyRegistry)),
            roundingPolicyRegistry: new RoundingPolicyRegistry(),
            clock: $this->clock,
        );
    }

    private function makeContext(?string $priceListId): PricingContext
    {
        return new PricingContext(
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            priceListId: null !== $priceListId ? PriceListId::fromString($priceListId) : null,
            isUrgency: false,
            animalSize: null,
            speciesGroup: null,
            discountCode: null,
            pricingDate: new \DateTimeImmutable('2026-08-20T10:00:00+00:00'),
        );
    }

    private function makePriceList(bool $isDefault, bool $withItem): PriceList
    {
        $items = [];

        if ($withItem) {
            $items[] = PriceListItem::create(
                id: PriceListItemId::fromString(self::ITEM_ID),
                itemRef: $this->actRef(),
                netPrice: Money::fromMinorUnits(1480, CurrencyCode::fromString('EUR')),
                taxCategoryOverride: null,
            );
        }

        return PriceList::reconstitute(
            id: PriceListId::fromString(self::PRICE_LIST_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            name: PriceListName::fromString('Tarifs standard'),
            isDefault: $isDefault,
            status: PriceListStatus::ACTIVE,
            items: $items,
            rules: [],
            createdAt: new \DateTimeImmutable('2026-01-01T00:00:00+00:00'),
            updatedAt: new \DateTimeImmutable('2026-01-01T00:00:00+00:00'),
        );
    }

    private function makeAct(): Act
    {
        return Act::create(
            id: ActId::fromString(self::ACT_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            name: ActName::fromString('Consultation standard'),
            code: ActCode::fromString('CONS-STD'),
            description: null,
            category: ActCategory::CONSULTATION,
            taxCategory: TaxCategoryCode::fromString('veterinary.act.consultation'),
            basePrice: Money::fromMinorUnits(5000, CurrencyCode::fromString('EUR')),
            estimatedDuration: ActDuration::ofMinutes(20),
            requiresAnesthesia: false,
            createdAt: new \DateTimeImmutable('2026-01-01T00:00:00+00:00'),
        );
    }

    private function actRef(): CatalogItemRef
    {
        return CatalogItemRef::toAct(ActId::fromString(self::ACT_ID));
    }
}
