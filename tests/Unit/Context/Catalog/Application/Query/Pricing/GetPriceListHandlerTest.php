<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Catalog\Application\Query\Pricing;

use App\Context\Catalog\Application\Query\Pricing\GetPriceList\GetPriceList;
use App\Context\Catalog\Application\Query\Pricing\GetPriceList\GetPriceListHandler;
use App\Context\Catalog\Domain\Pricing\PriceListItem;
use App\Context\Catalog\Domain\Pricing\Repository\PriceListRepositoryInterface;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListItemId;
use App\Context\Catalog\Domain\Shared\ValueObject\CatalogItemRef;
use App\Tests\Unit\Context\Catalog\Application\Command\CatalogCommandFixtures as F;
use PHPUnit\Framework\TestCase;

final class GetPriceListHandlerTest extends TestCase
{
    public function testAnItemPriceIsShownAsEnteredWithItsMode(): void
    {
        $item = PriceListItem::create(
            id: PriceListItemId::fromString('01960000-0000-7000-8000-00000000e001'),
            itemRef: CatalogItemRef::fromPrimitives('ACT', F::ACT_ID),
            price: F::eur(4500),
            taxCategoryOverride: null,
        );

        $repository = $this->createStub(PriceListRepositoryInterface::class);
        $repository->method('findById')->willReturn(F::priceList([$item]));

        $view = (new GetPriceListHandler($repository))(new GetPriceList(F::PRICE_LIST_ID, F::CLINIC_ID));

        self::assertCount(1, $view->items);
        self::assertSame(4500, $view->items[0]['priceMinorUnits']);
        self::assertSame('EUR', $view->items[0]['priceCurrency']);
        self::assertSame('incl', $view->items[0]['priceMode']);
    }
}
