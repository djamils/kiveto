<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Catalog\Application\Command\Pricing;

use App\Context\Catalog\Application\Command\Pricing\UpdatePriceListItem\UpdatePriceListItem;
use App\Context\Catalog\Application\Command\Pricing\UpdatePriceListItem\UpdatePriceListItemHandler;
use App\Context\Catalog\Domain\Pricing\Event\PriceListItemUpdated;
use App\Context\Catalog\Domain\Pricing\Exception\PriceListNotFoundException;
use App\Context\Catalog\Domain\Pricing\PriceListItem;
use App\Context\Catalog\Domain\Pricing\Repository\PriceListRepositoryInterface;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListItemId;
use App\Context\Catalog\Domain\Shared\ValueObject\CatalogItemRef;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\Context\Catalog\Application\Command\CatalogCommandFixtures as F;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UpdatePriceListItemHandler::class)]
#[CoversClass(UpdatePriceListItem::class)]
final class UpdatePriceListItemHandlerTest extends TestCase
{
    private const string ITEM_ID = '01960000-0000-7000-8000-00000000e001';

    public function testTheItemTakesItsNewPriceAndTaxOverride(): void
    {
        $item      = $this->item(null);
        $priceList = F::priceList([$item]);

        $repository = $this->createMock(PriceListRepositoryInterface::class);
        $repository->method('findById')->willReturn($priceList);
        $repository->expects(self::once())->method('save')->with($priceList);

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish')
            ->with([], self::isInstanceOf(PriceListItemUpdated::class))
        ;

        $this->handler($repository, $eventBus)(
            new UpdatePriceListItem(
                F::PRICE_LIST_ID,
                self::ITEM_ID,
                F::CLINIC_ID,
                5200,
                'EUR',
                'veterinary.act.surgery',
            ),
        );

        self::assertSame(5200, $item->netPrice()->minorUnits());
        self::assertSame('veterinary.act.surgery', $item->taxCategoryOverride()?->toString());
    }

    public function testClearingTheTaxOverrideFallsBackToTheCatalogCategory(): void
    {
        $item      = $this->item(TaxCategoryCode::fromString('veterinary.act.surgery'));
        $priceList = F::priceList([$item]);

        $repository = $this->createMock(PriceListRepositoryInterface::class);
        $repository->method('findById')->willReturn($priceList);
        $repository->expects(self::once())->method('save');

        $this->handler($repository, $this->createStub(EventBusInterface::class))(
            new UpdatePriceListItem(F::PRICE_LIST_ID, self::ITEM_ID, F::CLINIC_ID, 5200, 'EUR', null),
        );

        self::assertNull($item->taxCategoryOverride());
    }

    public function testAnUnknownPriceListIsRefused(): void
    {
        $repository = $this->createMock(PriceListRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);
        $repository->expects(self::never())->method('save');

        $this->expectException(PriceListNotFoundException::class);

        $this->handler($repository, $this->createStub(EventBusInterface::class))(
            new UpdatePriceListItem(F::PRICE_LIST_ID, self::ITEM_ID, F::CLINIC_ID, 5200, 'EUR', null),
        );
    }

    private function item(?TaxCategoryCode $taxOverride): PriceListItem
    {
        return PriceListItem::create(
            PriceListItemId::fromString(self::ITEM_ID),
            CatalogItemRef::fromPrimitives('ACT', F::ACT_ID),
            F::eur(4500),
            $taxOverride,
        );
    }

    private function handler(
        PriceListRepositoryInterface $repository,
        EventBusInterface $eventBus,
    ): UpdatePriceListItemHandler {
        return new UpdatePriceListItemHandler(
            $repository,
            new FrozenClock(F::now()),
            new DomainEventPublisher($eventBus),
        );
    }
}
