<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Catalog\Application\Command\Pricing;

use App\Context\Catalog\Application\Command\Pricing\RemovePriceListItem\RemovePriceListItem;
use App\Context\Catalog\Application\Command\Pricing\RemovePriceListItem\RemovePriceListItemHandler;
use App\Context\Catalog\Domain\Pricing\Event\PriceListItemRemoved;
use App\Context\Catalog\Domain\Pricing\Exception\PriceListNotFoundException;
use App\Context\Catalog\Domain\Pricing\PriceListItem;
use App\Context\Catalog\Domain\Pricing\Repository\PriceListRepositoryInterface;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListItemId;
use App\Context\Catalog\Domain\Shared\ValueObject\CatalogItemRef;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\Context\Catalog\Application\Command\CatalogCommandFixtures as F;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RemovePriceListItemHandler::class)]
#[CoversClass(RemovePriceListItem::class)]
final class RemovePriceListItemHandlerTest extends TestCase
{
    private const string KEPT_ITEM_ID    = '01960000-0000-7000-8000-00000000e001';
    private const string REMOVED_ITEM_ID = '01960000-0000-7000-8000-00000000e002';

    public function testTheItemLeavesThePriceList(): void
    {
        $priceList = F::priceList([
            PriceListItem::create(
                PriceListItemId::fromString(self::KEPT_ITEM_ID),
                CatalogItemRef::fromPrimitives('ACT', F::ACT_ID),
                F::eur(4500),
                null,
            ),
            PriceListItem::create(
                PriceListItemId::fromString(self::REMOVED_ITEM_ID),
                CatalogItemRef::fromPrimitives('ARTICLE', F::ARTICLE_ID),
                F::eur(300),
                null,
            ),
        ]);

        $repository = $this->createMock(PriceListRepositoryInterface::class);
        $repository->method('findById')->willReturn($priceList);
        $repository->expects(self::once())->method('save')->with($priceList);

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish')
            ->with([], self::isInstanceOf(PriceListItemRemoved::class))
        ;

        $this->handler($repository, $eventBus)(
            new RemovePriceListItem(F::PRICE_LIST_ID, self::REMOVED_ITEM_ID, F::CLINIC_ID),
        );

        self::assertCount(1, $priceList->items());
        self::assertSame(self::KEPT_ITEM_ID, $priceList->items()[0]->id()->toString());
    }

    public function testAnUnknownPriceListIsRefused(): void
    {
        $repository = $this->createMock(PriceListRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);
        $repository->expects(self::never())->method('save');

        $this->expectException(PriceListNotFoundException::class);

        $this->handler($repository, $this->createStub(EventBusInterface::class))(
            new RemovePriceListItem(F::PRICE_LIST_ID, self::REMOVED_ITEM_ID, F::CLINIC_ID),
        );
    }

    private function handler(
        PriceListRepositoryInterface $repository,
        EventBusInterface $eventBus,
    ): RemovePriceListItemHandler {
        return new RemovePriceListItemHandler(
            $repository,
            new FrozenClock(F::now()),
            new DomainEventPublisher($eventBus),
        );
    }
}
