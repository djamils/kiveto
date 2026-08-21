<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Catalog\Application\Query\Pricing;

use App\Context\Catalog\Application\Query\Pricing\ResolvePrice\ResolvePrice;
use App\Context\Catalog\Application\Query\Pricing\ResolvePrice\ResolvePriceHandler;
use App\Context\Catalog\Application\Service\PriceResolver;
use App\Context\Catalog\Domain\Act\Repository\ActRepositoryInterface;
use App\Context\Catalog\Domain\Act\ValueObject\ActId;
use App\Context\Catalog\Domain\Article\Repository\ArticleRepositoryInterface;
use App\Context\Catalog\Domain\Pricing\PriceList;
use App\Context\Catalog\Domain\Pricing\PriceListItem;
use App\Context\Catalog\Domain\Pricing\Repository\PriceListRepositoryInterface;
use App\Context\Catalog\Domain\Pricing\Service\PriceCalculator;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListId;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListItemId;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListName;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListStatus;
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
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

final class ResolvePriceHandlerTest extends TestCase
{
    private const string CLINIC_ID     = '01950000-0000-7000-0000-0000000000c1';
    private const string PRICE_LIST_ID = '01950000-0000-7000-0000-0000000000a2';
    private const string ACT_ID        = '01950000-0000-7000-0000-0000000000d4';

    private ClockInterface&Stub $clock;

    protected function setUp(): void
    {
        $this->clock = $this->createStub(ClockInterface::class);
        $this->clock->method('now')->willReturn(new \DateTimeImmutable('2026-08-20T10:00:00+00:00'));
    }

    public function testResolvesAgainstRequestedPriceList(): void
    {
        $priceListRepository = $this->createMock(PriceListRepositoryInterface::class);
        $priceListRepository
            ->expects(self::once())
            ->method('findById')
            ->with(
                self::callback(static fn (PriceListId $id) => self::PRICE_LIST_ID === $id->toString()),
                self::callback(static fn (ClinicId $id) => self::CLINIC_ID === $id->toString()),
            )
            ->willReturn($this->makePriceList())
        ;
        $priceListRepository->expects(self::never())->method('findDefaultForClinic');

        $resolved = ($this->makeHandler($priceListRepository))(new ResolvePrice(
            clinicId: self::CLINIC_ID,
            priceListId: self::PRICE_LIST_ID,
            itemType: 'ACT',
            itemId: self::ACT_ID,
        ));

        self::assertSame(1480, $resolved->netAmount->minorUnits());
        self::assertSame(self::PRICE_LIST_ID, $resolved->sourcePriceList->toString());
    }

    public function testFallsBackToDefaultListWhenNoPriceListRequested(): void
    {
        $priceListRepository = $this->createMock(PriceListRepositoryInterface::class);
        $priceListRepository
            ->expects(self::once())
            ->method('findDefaultForClinic')
            ->with(self::callback(static fn (ClinicId $id) => self::CLINIC_ID === $id->toString()))
            ->willReturn($this->makePriceList())
        ;
        $priceListRepository->expects(self::never())->method('findById');

        $resolved = ($this->makeHandler($priceListRepository))(new ResolvePrice(
            clinicId: self::CLINIC_ID,
            priceListId: null,
            itemType: 'ACT',
            itemId: self::ACT_ID,
        ));

        self::assertSame(1480, $resolved->netAmount->minorUnits());
    }

    private function makeHandler(PriceListRepositoryInterface&MockObject $priceListRepository): ResolvePriceHandler
    {
        $eur = Currency::of(
            code: CurrencyCode::fromString('EUR'),
            symbol: CurrencySymbol::fromString('€'),
            decimals: CurrencyDecimals::of(2),
            displayName: 'Euro',
        );

        $currencyRegistry = $this->createStub(CurrencyRegistry::class);
        $currencyRegistry->method('get')->willReturn($eur);

        $priceResolver = new PriceResolver(
            priceListRepository: $priceListRepository,
            actRepository: $this->createStub(ActRepositoryInterface::class),
            articleRepository: $this->createStub(ArticleRepositoryInterface::class),
            priceCalculator: new PriceCalculator(new MoneyCalculator($currencyRegistry)),
            roundingPolicyRegistry: new RoundingPolicyRegistry(),
            clock: $this->clock,
        );

        return new ResolvePriceHandler($priceResolver, $this->clock);
    }

    private function makePriceList(): PriceList
    {
        $item = PriceListItem::create(
            id: PriceListItemId::fromString('01950000-0000-7000-0000-0000000000b3'),
            itemRef: CatalogItemRef::toAct(ActId::fromString(self::ACT_ID)),
            netPrice: Money::fromMinorUnits(1480, CurrencyCode::fromString('EUR')),
            taxCategoryOverride: null,
        );

        return PriceList::reconstitute(
            id: PriceListId::fromString(self::PRICE_LIST_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            name: PriceListName::fromString('Tarifs standard'),
            isDefault: false,
            status: PriceListStatus::ACTIVE,
            items: [$item],
            rules: [],
            createdAt: new \DateTimeImmutable('2026-01-01T00:00:00+00:00'),
            updatedAt: new \DateTimeImmutable('2026-01-01T00:00:00+00:00'),
        );
    }
}
