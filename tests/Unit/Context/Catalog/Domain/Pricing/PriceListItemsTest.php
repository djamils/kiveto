<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Catalog\Domain\Pricing;

use App\Context\Catalog\Domain\Pricing\Event\PriceListItemUpdated;
use App\Context\Catalog\Domain\Pricing\PriceList;
use App\Context\Catalog\Domain\Pricing\PriceListItem;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListItemId;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListStatus;
use App\Context\Catalog\Domain\Shared\ValueObject\CatalogItemRef;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use App\Tests\Unit\Context\Catalog\Application\Command\CatalogCommandFixtures as F;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PriceList::class)]
#[CoversClass(PriceListItem::class)]
final class PriceListItemsTest extends TestCase
{
    private const string ITEM_ID = '01960000-0000-7000-8000-00000000e001';

    public function testAReconstitutedPriceListExposesItsState(): void
    {
        $priceList = F::priceList();

        self::assertSame(F::CLINIC_ID, $priceList->clinicId()->toString());
        self::assertSame('Tarif standard', $priceList->name()->toString());
        self::assertTrue($priceList->isDefault());
        self::assertSame(PriceListStatus::ACTIVE, $priceList->status());
        self::assertSame([], $priceList->rules());
        self::assertSame('2026-01-01 10:00:00', $priceList->createdAt()->format('Y-m-d H:i:s'));
        self::assertSame('2026-01-01 10:00:00', $priceList->updatedAt()->format('Y-m-d H:i:s'));
    }

    public function testAnItemCanBeRepricedInPlace(): void
    {
        $item      = $this->item();
        $priceList = F::priceList([$item]);

        $priceList->updateItem(
            PriceListItemId::fromString(self::ITEM_ID),
            F::eur(5200),
            TaxCategoryCode::fromString('veterinary.act.surgery'),
            F::now(),
        );

        self::assertSame(5200, $item->netPrice()->minorUnits());
        self::assertSame('veterinary.act.surgery', $item->taxCategoryOverride()?->toString());
        self::assertSame(F::now(), $priceList->updatedAt());

        $events = $priceList->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PriceListItemUpdated::class, $events[0]);
    }

    public function testUpdatingOrRemovingAnUnknownItemChangesNothing(): void
    {
        $priceList = F::priceList([$this->item()]);
        $unknown   = PriceListItemId::fromString('01960000-0000-7000-8000-00000000e099');

        $priceList->updateItem($unknown, F::eur(5200), null, F::now());
        $priceList->removeItem($unknown, F::now());

        self::assertCount(1, $priceList->items());
        self::assertSame(4500, $priceList->items()[0]->netPrice()->minorUnits());
        self::assertSame([], $priceList->pullDomainEvents());
    }

    public function testThePriceListCanBeMarkedAndUnmarkedAsDefault(): void
    {
        $priceList = F::priceList();

        $priceList->unmarkAsDefault(F::now());
        self::assertFalse($priceList->isDefault());

        $priceList->markAsDefault(F::now());
        self::assertTrue($priceList->isDefault());
        self::assertSame(F::now(), $priceList->updatedAt());
    }

    public function testANegativeNetPriceIsRefusedAtCreation(): void
    {
        $this->expectException(\DomainException::class);

        PriceListItem::create(
            PriceListItemId::fromString(self::ITEM_ID),
            CatalogItemRef::fromPrimitives('ACT', F::ACT_ID),
            F::eur(-1),
            null,
        );
    }

    public function testANegativeNetPriceIsRefusedOnUpdate(): void
    {
        $item = $this->item();

        $this->expectException(\DomainException::class);

        $item->updatePrice(F::eur(-1), null);
    }

    private function item(): PriceListItem
    {
        return PriceListItem::create(
            PriceListItemId::fromString(self::ITEM_ID),
            CatalogItemRef::fromPrimitives('ACT', F::ACT_ID),
            F::eur(4500),
            null,
        );
    }
}
