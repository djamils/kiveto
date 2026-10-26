<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Catalog\Domain;

use App\Context\Catalog\Domain\Act\Event\ActArchived;
use App\Context\Catalog\Domain\Act\Event\ActBasePriceChanged;
use App\Context\Catalog\Domain\Act\Event\ActCreated;
use App\Context\Catalog\Domain\Act\Event\ActRenamed;
use App\Context\Catalog\Domain\Act\Event\ActRestored;
use App\Context\Catalog\Domain\Act\Event\ActTaxCategoryChanged;
use App\Context\Catalog\Domain\Article\Event\ArticleArchived;
use App\Context\Catalog\Domain\Article\Event\ArticleBasePriceChanged;
use App\Context\Catalog\Domain\Article\Event\ArticleCreated;
use App\Context\Catalog\Domain\Article\Event\ArticlePrescriptionFlagsUpdated;
use App\Context\Catalog\Domain\Article\Event\ArticleRenamed;
use App\Context\Catalog\Domain\Article\Event\ArticleRestored;
use App\Context\Catalog\Domain\Article\Event\ArticleTaxCategoryChanged;
use App\Context\Catalog\Domain\Package\Event\PackageArchived;
use App\Context\Catalog\Domain\Package\Event\PackageComponentAdded;
use App\Context\Catalog\Domain\Package\Event\PackageComponentRemoved;
use App\Context\Catalog\Domain\Package\Event\PackageCreated;
use App\Context\Catalog\Domain\Package\Event\PackageFixedPriceChanged;
use App\Context\Catalog\Domain\Package\Event\PackageRestored;
use App\Context\Catalog\Domain\Pricing\Event\PriceListCreated;
use App\Context\Catalog\Domain\Pricing\Event\PriceListItemAdded;
use App\Context\Catalog\Domain\Pricing\Event\PriceListItemRemoved;
use App\Context\Catalog\Domain\Pricing\Event\PriceListItemUpdated;
use App\Context\Catalog\Domain\Pricing\Event\PriceRuleAdded;
use App\Context\Catalog\Domain\Pricing\Event\PriceRuleRemoved;
use App\Shared\Domain\Event\AbstractDomainEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ActArchived::class)]
#[CoversClass(ActRestored::class)]
#[CoversClass(ActBasePriceChanged::class)]
#[CoversClass(ActCreated::class)]
#[CoversClass(ActRenamed::class)]
#[CoversClass(ActTaxCategoryChanged::class)]
#[CoversClass(ArticleArchived::class)]
#[CoversClass(ArticleRestored::class)]
#[CoversClass(ArticleBasePriceChanged::class)]
#[CoversClass(ArticleCreated::class)]
#[CoversClass(ArticlePrescriptionFlagsUpdated::class)]
#[CoversClass(ArticleRenamed::class)]
#[CoversClass(ArticleTaxCategoryChanged::class)]
#[CoversClass(PackageArchived::class)]
#[CoversClass(PackageRestored::class)]
#[CoversClass(PackageComponentAdded::class)]
#[CoversClass(PackageComponentRemoved::class)]
#[CoversClass(PackageCreated::class)]
#[CoversClass(PackageFixedPriceChanged::class)]
#[CoversClass(PriceListCreated::class)]
#[CoversClass(PriceListItemAdded::class)]
#[CoversClass(PriceListItemRemoved::class)]
#[CoversClass(PriceListItemUpdated::class)]
#[CoversClass(PriceRuleAdded::class)]
#[CoversClass(PriceRuleRemoved::class)]
final class CatalogDomainEventsTest extends TestCase
{
    private const string AGGREGATE_ID = '01960000-0000-7000-8000-00000000b001';
    private const string CLINIC_ID    = '01960000-0000-7000-8000-00000000b002';

    /** @param \Closure(): AbstractDomainEvent $build */
    #[DataProvider('provideAnEventIsKeyedByItsAggregateAndCarriesAllItsFieldsCases')]
    public function testAnEventIsKeyedByItsAggregateAndCarriesAllItsFields(\Closure $build): void
    {
        $event = $build();

        // The aggregate id is always the first constructor argument.
        self::assertSame(self::AGGREGATE_ID, $event->aggregateId());
        // The payload mirrors every public field of the event, in declaration order.
        self::assertSame(get_object_vars($event), $event->payload());
        self::assertStringStartsWith('catalog.', $event->name());
    }

    /** @return iterable<string, array{\Closure(): AbstractDomainEvent}> */
    public static function provideAnEventIsKeyedByItsAggregateAndCarriesAllItsFieldsCases(): iterable
    {
        yield 'ActArchived' => [
            static fn (): AbstractDomainEvent => new ActArchived(
                self::AGGREGATE_ID,
                self::CLINIC_ID,
            ),
        ];
        yield 'ActRestored' => [
            static fn (): AbstractDomainEvent => new ActRestored(
                self::AGGREGATE_ID,
                self::CLINIC_ID,
            ),
        ];
        yield 'ActBasePriceChanged' => [
            static fn (): AbstractDomainEvent => new ActBasePriceChanged(
                self::AGGREGATE_ID,
                self::CLINIC_ID,
                6500,
                'EUR',
                'incl',
            ),
        ];
        yield 'ActCreated' => [
            static fn (): AbstractDomainEvent => new ActCreated(
                self::AGGREGATE_ID,
                self::CLINIC_ID,
                'Consultation',
                'CONS',
                'CONSULTATION',
                'veterinary.act.consultation',
                5000,
                'EUR',
                'incl',
                20,
                false,
            ),
        ];
        yield 'ActRenamed' => [
            static fn (): AbstractDomainEvent => new ActRenamed(
                self::AGGREGATE_ID,
                self::CLINIC_ID,
                'Consultation de suivi',
            ),
        ];
        yield 'ActTaxCategoryChanged' => [
            static fn (): AbstractDomainEvent => new ActTaxCategoryChanged(
                self::AGGREGATE_ID,
                self::CLINIC_ID,
                'veterinary.act.surgery',
            ),
        ];
        yield 'ArticleArchived' => [
            static fn (): AbstractDomainEvent => new ArticleArchived(
                self::AGGREGATE_ID,
                self::CLINIC_ID,
            ),
        ];
        yield 'ArticleRestored' => [
            static fn (): AbstractDomainEvent => new ArticleRestored(
                self::AGGREGATE_ID,
                self::CLINIC_ID,
            ),
        ];
        yield 'ArticleBasePriceChanged' => [
            static fn (): AbstractDomainEvent => new ArticleBasePriceChanged(
                self::AGGREGATE_ID,
                self::CLINIC_ID,
                420,
                'EUR',
                'incl',
            ),
        ];
        yield 'ArticleCreated' => [
            static fn (): AbstractDomainEvent => new ArticleCreated(
                self::AGGREGATE_ID,
                self::CLINIC_ID,
                'Compresses',
                'COMP-01',
                'CONSUMABLE',
                '3760123456789',
                'veterinary.consumable',
                350,
                'EUR',
                'incl',
                'UNIT',
                true,
            ),
        ];
        yield 'ArticlePrescriptionFlagsUpdated' => [
            static fn (): AbstractDomainEvent => new ArticlePrescriptionFlagsUpdated(
                self::AGGREGATE_ID,
                self::CLINIC_ID,
                true,
                'RX',
                false,
            ),
        ];
        yield 'ArticleRenamed' => [
            static fn (): AbstractDomainEvent => new ArticleRenamed(
                self::AGGREGATE_ID,
                self::CLINIC_ID,
                'Compresses non tissées',
            ),
        ];
        yield 'ArticleTaxCategoryChanged' => [
            static fn (): AbstractDomainEvent => new ArticleTaxCategoryChanged(
                self::AGGREGATE_ID,
                self::CLINIC_ID,
                'veterinary.food',
            ),
        ];
        yield 'PackageArchived' => [
            static fn (): AbstractDomainEvent => new PackageArchived(
                self::AGGREGATE_ID,
                self::CLINIC_ID,
            ),
        ];
        yield 'PackageRestored' => [
            static fn (): AbstractDomainEvent => new PackageRestored(
                self::AGGREGATE_ID,
                self::CLINIC_ID,
            ),
        ];
        yield 'PackageComponentAdded' => [
            static fn (): AbstractDomainEvent => new PackageComponentAdded(
                self::AGGREGATE_ID,
                '01960000-0000-7000-8000-00000000d001',
                'ACT',
                '01960000-0000-7000-8000-00000000c002',
                2,
                10,
            ),
        ];
        yield 'PackageComponentRemoved' => [
            static fn (): AbstractDomainEvent => new PackageComponentRemoved(
                self::AGGREGATE_ID,
                '01960000-0000-7000-8000-00000000d001',
            ),
        ];
        yield 'PackageCreated' => [
            static fn (): AbstractDomainEvent => new PackageCreated(
                self::AGGREGATE_ID,
                self::CLINIC_ID,
                'Forfait stérilisation',
                'PKG-STER',
                'FIXED_PRICE',
            ),
        ];
        yield 'PackageFixedPriceChanged' => [
            static fn (): AbstractDomainEvent => new PackageFixedPriceChanged(
                self::AGGREGATE_ID,
                self::CLINIC_ID,
                27500,
                'EUR',
                'incl',
            ),
        ];
        yield 'PriceListCreated' => [
            static fn (): AbstractDomainEvent => new PriceListCreated(
                self::AGGREGATE_ID,
                self::CLINIC_ID,
                'Tarif standard',
                true,
            ),
        ];
        yield 'PriceListItemAdded' => [
            static fn (): AbstractDomainEvent => new PriceListItemAdded(
                self::AGGREGATE_ID,
                '01960000-0000-7000-8000-00000000e001',
                'ARTICLE',
                '01960000-0000-7000-8000-00000000c003',
                300,
                'EUR',
                'incl',
            ),
        ];
        yield 'PriceListItemRemoved' => [
            static fn (): AbstractDomainEvent => new PriceListItemRemoved(
                self::AGGREGATE_ID,
                '01960000-0000-7000-8000-00000000e001',
            ),
        ];
        yield 'PriceListItemUpdated' => [
            static fn (): AbstractDomainEvent => new PriceListItemUpdated(
                self::AGGREGATE_ID,
                '01960000-0000-7000-8000-00000000e001',
                5200,
                'EUR',
                'incl',
            ),
        ];
        yield 'PriceRuleAdded' => [
            static fn (): AbstractDomainEvent => new PriceRuleAdded(
                self::AGGREGATE_ID,
                '01960000-0000-7000-8000-00000000f001',
                'SPECIES',
            ),
        ];
        yield 'PriceRuleRemoved' => [
            static fn (): AbstractDomainEvent => new PriceRuleRemoved(
                self::AGGREGATE_ID,
                '01960000-0000-7000-8000-00000000f001',
            ),
        ];
    }
}
