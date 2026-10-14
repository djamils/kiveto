<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Catalog\Domain;

use App\Context\Catalog\Domain\Act\Exception\InvalidActNameException;
use App\Context\Catalog\Domain\Act\ValueObject\ActCode;
use App\Context\Catalog\Domain\Act\ValueObject\ActDescription;
use App\Context\Catalog\Domain\Act\ValueObject\ActDuration;
use App\Context\Catalog\Domain\Act\ValueObject\ActId;
use App\Context\Catalog\Domain\Act\ValueObject\ActName;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleCode;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleId;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleName;
use App\Context\Catalog\Domain\Article\ValueObject\DrugProperties;
use App\Context\Catalog\Domain\Article\ValueObject\Gtin;
use App\Context\Catalog\Domain\Article\ValueObject\MarketingAuthorizationRef;
use App\Context\Catalog\Domain\Article\ValueObject\PrescriptionClass;
use App\Context\Catalog\Domain\Package\ValueObject\PackageCode;
use App\Context\Catalog\Domain\Package\ValueObject\PackageComponentId;
use App\Context\Catalog\Domain\Package\ValueObject\PackageName;
use App\Context\Catalog\Domain\Package\ValueObject\Quantity;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListName;
use App\Context\Catalog\Domain\Shared\ValueObject\CatalogItemRef;
use App\Context\Catalog\Domain\Shared\ValueObject\CatalogItemType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ActCode::class)]
#[CoversClass(ActDescription::class)]
#[CoversClass(ActDuration::class)]
#[CoversClass(ActName::class)]
#[CoversClass(ArticleCode::class)]
#[CoversClass(ArticleName::class)]
#[CoversClass(DrugProperties::class)]
#[CoversClass(Gtin::class)]
#[CoversClass(MarketingAuthorizationRef::class)]
#[CoversClass(PackageCode::class)]
#[CoversClass(PackageComponentId::class)]
#[CoversClass(PackageName::class)]
#[CoversClass(Quantity::class)]
#[CoversClass(PriceListName::class)]
#[CoversClass(CatalogItemRef::class)]
final class CatalogValueObjectsTest extends TestCase
{
    /**
     * @param \Closure(): mixed        $build
     * @param class-string<\Throwable> $expectedException
     */
    #[DataProvider('provideAnInvalidValueIsRefusedCases')]
    public function testAnInvalidValueIsRefused(\Closure $build, string $expectedException): void
    {
        $this->expectException($expectedException);

        $build();
    }

    /** @return iterable<string, array{\Closure(): mixed, class-string<\Throwable>}> */
    public static function provideAnInvalidValueIsRefusedCases(): iterable
    {
        yield 'lowercase act code' => [static fn (): ActCode => ActCode::fromString('cons'), \DomainException::class];
        yield 'too long act description' => [
            static fn (): ActDescription => ActDescription::fromString(str_repeat('a', 1001)),
            \DomainException::class,
        ];
        yield 'zero act duration' => [static fn (): ActDuration => ActDuration::ofMinutes(0), \DomainException::class];
        yield 'blank act name' => [static fn (): ActName => ActName::fromString('  '), InvalidActNameException::class];
        yield 'too long act name' => [
            static fn (): ActName => ActName::fromString(str_repeat('a', 151)),
            InvalidActNameException::class,
        ];
        yield 'one-letter article code' => [
            static fn (): ArticleCode => ArticleCode::fromString('A'),
            \DomainException::class,
        ];
        yield 'blank article name' => [
            static fn (): ArticleName => ArticleName::fromString(''),
            \DomainException::class,
        ];
        yield 'too long article name' => [
            static fn (): ArticleName => ArticleName::fromString(str_repeat('a', 151)),
            \DomainException::class,
        ];
        yield 'short gtin' => [static fn (): Gtin => Gtin::fromString('1234567'), \DomainException::class];
        yield 'blank authorization ref' => [
            static fn (): MarketingAuthorizationRef => MarketingAuthorizationRef::fromString('   '),
            \DomainException::class,
        ];
        yield 'package code with spaces' => [
            static fn (): PackageCode => PackageCode::fromString('PKG STER'),
            \DomainException::class,
        ];
        yield 'blank package name' => [
            static fn (): PackageName => PackageName::fromString(' '),
            \DomainException::class,
        ];
        yield 'too long package name' => [
            static fn (): PackageName => PackageName::fromString(str_repeat('a', 151)),
            \DomainException::class,
        ];
        yield 'zero quantity' => [static fn (): Quantity => Quantity::of(0), \DomainException::class];
        yield 'blank price list name' => [
            static fn (): PriceListName => PriceListName::fromString(''),
            \DomainException::class,
        ];
    }

    public function testCodesNamesAndQuantitiesCompareByValue(): void
    {
        self::assertTrue(ActCode::fromString('CONS')->equals(ActCode::fromString('CONS')));
        self::assertFalse(ActCode::fromString('CONS')->equals(ActCode::fromString('VACC')));
        self::assertTrue(ActDuration::ofMinutes(20)->equals(ActDuration::ofMinutes(20)));
        self::assertSame(20, ActDuration::ofMinutes(20)->toMinutes());
        self::assertTrue(ActName::fromString(' Consultation ')->equals(ActName::fromString('Consultation')));
        self::assertTrue(ArticleCode::fromString('AMOX')->equals(ArticleCode::fromString('AMOX')));
        self::assertTrue(ArticleName::fromString('Amoxicilline')->equals(ArticleName::fromString('Amoxicilline')));
        self::assertTrue(Gtin::fromString('3760123456789')->equals(Gtin::fromString('3760123456789')));
        self::assertTrue(PackageCode::fromString('PKG')->equals(PackageCode::fromString('PKG')));
        self::assertSame('PKG', PackageCode::fromString('PKG')->toString());
        self::assertTrue(PackageName::fromString('Forfait')->equals(PackageName::fromString('Forfait')));
        self::assertTrue(Quantity::of(2)->equals(Quantity::of(2)));
        self::assertSame(2, Quantity::of(2)->value());
        self::assertFalse(Quantity::of(2)->equals(Quantity::of(3)));
        self::assertTrue(PriceListName::fromString('Tarif')->equals(PriceListName::fromString('Tarif')));
    }

    public function testAnActDescriptionIsOptional(): void
    {
        self::assertNull(ActDescription::fromNullableString(null));
        self::assertNull(ActDescription::fromNullableString('   '));

        $description = ActDescription::fromNullableString('Examen clinique complet');

        self::assertNotNull($description);
        self::assertSame('Examen clinique complet', $description->toString());
        self::assertTrue($description->equals(ActDescription::fromString('Examen clinique complet')));
    }

    public function testAMarketingAuthorizationRefIsTrimmed(): void
    {
        $ref = MarketingAuthorizationRef::fromString('  FR/V/1234567  ');

        self::assertSame('FR/V/1234567', $ref->toString());
        self::assertTrue($ref->equals(MarketingAuthorizationRef::fromString('FR/V/1234567')));
    }

    public function testDrugPropertiesExposeTheirAuthorizationAndControlledSubstanceFlag(): void
    {
        $authorization = MarketingAuthorizationRef::fromString('FR/V/1234567');
        $properties    = DrugProperties::create($authorization, true, PrescriptionClass::RX_CONTROLLED, true);

        self::assertSame($authorization, $properties->authorizationRef());
        self::assertTrue($properties->isControlledSubstance());
        self::assertFalse($properties->withUpdatedFlags(false, null, false)->isControlledSubstance());
    }

    public function testAPackageComponentIdKeepsItsUuid(): void
    {
        $id = PackageComponentId::fromString('01960000-0000-7000-8000-00000000d001');

        self::assertSame('01960000-0000-7000-8000-00000000d001', $id->toString());
    }

    public function testACatalogItemRefKnowsWhatItPointsTo(): void
    {
        $act     = CatalogItemRef::fromPrimitives('ACT', '01960000-0000-7000-8000-00000000c002');
        $article = CatalogItemRef::fromPrimitives('ARTICLE', '01960000-0000-7000-8000-00000000c003');

        self::assertSame(CatalogItemType::ACT, $act->type());
        self::assertTrue($act->isAct());
        self::assertFalse($act->isArticle());
        self::assertTrue($article->isArticle());
        self::assertFalse($article->isAct());
        self::assertTrue($act->equals(CatalogItemRef::fromPrimitives('ACT', '01960000-0000-7000-8000-00000000c002')));
        self::assertFalse($act->equals($article));
        self::assertTrue(
            CatalogItemRef::toAct(ActId::fromString('01960000-0000-7000-8000-00000000c002'))->equals($act),
        );
        self::assertTrue(
            CatalogItemRef::toArticle(ArticleId::fromString('01960000-0000-7000-8000-00000000c003'))->equals($article),
        );
    }
}
