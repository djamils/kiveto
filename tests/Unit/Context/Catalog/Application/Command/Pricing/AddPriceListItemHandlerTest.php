<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Catalog\Application\Command\Pricing;

use App\Context\Catalog\Application\Command\Pricing\AddPriceListItem\AddPriceListItem;
use App\Context\Catalog\Application\Command\Pricing\AddPriceListItem\AddPriceListItemHandler;
use App\Context\Catalog\Domain\Pricing\Event\PriceListItemAdded;
use App\Context\Catalog\Domain\Pricing\Exception\PriceListNotFoundException;
use App\Context\Catalog\Domain\Pricing\Repository\PriceListRepositoryInterface;
use App\Context\Catalog\Domain\Shared\ValueObject\CatalogItemType;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\Context\Catalog\Application\Command\CatalogCommandFixtures as F;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AddPriceListItemHandler::class)]
#[CoversClass(AddPriceListItem::class)]
final class AddPriceListItemHandlerTest extends TestCase
{
    public function testAnItemWithATaxOverrideJoinsThePriceList(): void
    {
        $priceList = F::priceList();

        $repository = $this->createMock(PriceListRepositoryInterface::class);
        $repository->method('findById')->willReturn($priceList);
        $repository->expects(self::once())->method('save')->with($priceList);

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish')
            ->with([], self::isInstanceOf(PriceListItemAdded::class))
        ;

        $this->handler($repository, $eventBus)(new AddPriceListItem(
            priceListId: F::PRICE_LIST_ID,
            clinicId: F::CLINIC_ID,
            itemType: 'ACT',
            itemId: F::ACT_ID,
            netPriceMinorUnits: 4500,
            netPriceCurrency: 'EUR',
            taxCategoryOverride: 'veterinary.act.surgery',
        ));

        self::assertCount(1, $priceList->items());
        $item = $priceList->items()[0];
        self::assertSame(F::GENERATED_ID, $item->id()->toString());
        self::assertSame(CatalogItemType::ACT, $item->itemRef()->type());
        self::assertSame(4500, $item->netPrice()->minorUnits());
        self::assertSame('veterinary.act.surgery', $item->taxCategoryOverride()?->toString());
    }

    public function testAnItemWithoutTaxOverrideKeepsTheCatalogTaxCategory(): void
    {
        $priceList = F::priceList();

        $repository = $this->createMock(PriceListRepositoryInterface::class);
        $repository->method('findById')->willReturn($priceList);
        $repository->expects(self::once())->method('save');

        $this->handler($repository, $this->createStub(EventBusInterface::class))(new AddPriceListItem(
            priceListId: F::PRICE_LIST_ID,
            clinicId: F::CLINIC_ID,
            itemType: 'ARTICLE',
            itemId: F::ARTICLE_ID,
            netPriceMinorUnits: 300,
            netPriceCurrency: 'EUR',
            taxCategoryOverride: null,
        ));

        self::assertNull($priceList->items()[0]->taxCategoryOverride());
    }

    public function testAnUnknownPriceListIsRefused(): void
    {
        $repository = $this->createMock(PriceListRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);
        $repository->expects(self::never())->method('save');

        $this->expectException(PriceListNotFoundException::class);

        $this->handler($repository, $this->createStub(EventBusInterface::class))(new AddPriceListItem(
            priceListId: F::PRICE_LIST_ID,
            clinicId: F::CLINIC_ID,
            itemType: 'ACT',
            itemId: F::ACT_ID,
            netPriceMinorUnits: 4500,
            netPriceCurrency: 'EUR',
            taxCategoryOverride: null,
        ));
    }

    private function handler(
        PriceListRepositoryInterface $repository,
        EventBusInterface $eventBus,
    ): AddPriceListItemHandler {
        $uuidGenerator = $this->createStub(UuidGeneratorInterface::class);
        $uuidGenerator->method('generate')->willReturn(F::GENERATED_ID);

        return new AddPriceListItemHandler(
            $repository,
            $uuidGenerator,
            new FrozenClock(F::now()),
            new DomainEventPublisher($eventBus),
        );
    }
}
