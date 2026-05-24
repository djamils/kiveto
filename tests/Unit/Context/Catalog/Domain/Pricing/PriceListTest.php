<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Catalog\Domain\Pricing;

use App\Context\Catalog\Domain\Pricing\Event\PriceListCreated;
use App\Context\Catalog\Domain\Pricing\Event\PriceListItemAdded;
use App\Context\Catalog\Domain\Pricing\Event\PriceListItemRemoved;
use App\Context\Catalog\Domain\Pricing\Event\PriceRuleAdded;
use App\Context\Catalog\Domain\Pricing\Event\PriceRuleRemoved;
use App\Context\Catalog\Domain\Pricing\PriceList;
use App\Context\Catalog\Domain\Pricing\PriceListItem;
use App\Context\Catalog\Domain\Pricing\PriceRule;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceAdjustment;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListId;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListItemId;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListName;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceRuleCondition;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceRuleId;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceRuleType;
use App\Context\Catalog\Domain\Shared\ValueObject\CatalogItemRef;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class PriceListTest extends TestCase
{
    public function testCreatePriceList(): void
    {
        $now = new \DateTimeImmutable('2026-01-01T10:00:00+00:00');
        $pl  = PriceList::create(
            id: PriceListId::fromString('01950000-0000-7000-0000-000000000001'),
            clinicId: ClinicId::fromString('01950000-0000-7000-0000-000000000002'),
            name: PriceListName::fromString('Test'),
            isDefault: false,
            createdAt: $now,
        );

        $events = $pl->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(PriceListCreated::class, $events[0]);
        self::assertSame('Test', $events[0]->name);
        self::assertFalse($events[0]->isDefault);
    }

    public function testAddItem(): void
    {
        $now  = new \DateTimeImmutable('2026-01-01T10:00:00+00:00');
        $pl   = $this->makePriceList();
        $item = $this->makeItem();

        $pl->addItem($item, $now);

        $events = $pl->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(PriceListItemAdded::class, $events[0]);
        self::assertCount(1, $pl->items());
    }

    public function testRemoveItem(): void
    {
        $now  = new \DateTimeImmutable('2026-01-01T10:00:00+00:00');
        $pl   = $this->makePriceList();
        $item = $this->makeItem();

        $pl->addItem($item, $now);
        $_ = $pl->pullDomainEvents();

        $pl->removeItem($item->id(), $now);

        $events = $pl->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(PriceListItemRemoved::class, $events[0]);
        self::assertCount(0, $pl->items());
    }

    public function testAddRule(): void
    {
        $now  = new \DateTimeImmutable('2026-01-01T10:00:00+00:00');
        $pl   = $this->makePriceList();
        $rule = $this->makeRule();

        $pl->addRule($rule, $now);

        $events = $pl->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(PriceRuleAdded::class, $events[0]);
        self::assertCount(1, $pl->rules());
    }

    public function testRemoveRule(): void
    {
        $now  = new \DateTimeImmutable('2026-01-01T10:00:00+00:00');
        $pl   = $this->makePriceList();
        $rule = $this->makeRule();

        $pl->addRule($rule, $now);
        $_ = $pl->pullDomainEvents();

        $pl->removeRule($rule->id(), $now);

        $events = $pl->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(PriceRuleRemoved::class, $events[0]);
        self::assertCount(0, $pl->rules());
    }

    private function makePriceList(): PriceList
    {
        $now = new \DateTimeImmutable('2026-01-01T10:00:00+00:00');
        $pl  = PriceList::create(
            id: PriceListId::fromString('01950000-0000-7000-0000-000000000001'),
            clinicId: ClinicId::fromString('01950000-0000-7000-0000-000000000002'),
            name: PriceListName::fromString('Standard'),
            isDefault: true,
            createdAt: $now,
        );
        $_ = $pl->pullDomainEvents();

        return $pl;
    }

    private function makeItem(): PriceListItem
    {
        return PriceListItem::create(
            id: PriceListItemId::fromString('01950000-0000-7000-0000-000000000003'),
            itemRef: CatalogItemRef::fromPrimitives('ACT', '01950000-0000-7000-0000-000000000010'),
            netPrice: Money::fromMinorUnits(5000, CurrencyCode::fromString('EUR')),
            taxCategoryOverride: null,
        );
    }

    private function makeRule(): PriceRule
    {
        return PriceRule::create(
            id: PriceRuleId::fromString('01950000-0000-7000-0000-000000000004'),
            ruleType: PriceRuleType::URGENCY_COEFFICIENT,
            condition: PriceRuleCondition::create(true, [], true, null, null, null),
            adjustment: PriceAdjustment::coefficient('1.5'),
        );
    }
}
