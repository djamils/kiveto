<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Catalog\Infrastructure\Persistence\Doctrine\Mapper;

use App\Context\Catalog\Domain\Article\Article;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleCode;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleId;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleKind;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleName;
use App\Context\Catalog\Domain\Package\ValueObject\PackagePricingMode;
use App\Context\Catalog\Domain\ValueObject\CatalogPrice;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Context\Catalog\Domain\ValueObject\PriceEntryMode;
use App\Context\Catalog\Infrastructure\Persistence\Doctrine\Mapper\ArticleMapper;
use App\Context\Catalog\Infrastructure\Persistence\Doctrine\Mapper\PackageMapper;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use App\Tests\Unit\Context\Catalog\Application\Command\CatalogCommandFixtures as F;
use PHPUnit\Framework\TestCase;

/**
 * A stored price comes back with the amount and the mode it was entered in.
 */
final class CatalogPriceMappingTest extends TestCase
{
    public function testAnArticlePriceEnteredExcludingTaxSurvivesTheRoundTrip(): void
    {
        $article = Article::createNonDrug(
            id: ArticleId::fromString(F::ARTICLE_ID),
            clinicId: ClinicId::fromString(F::CLINIC_ID),
            name: ArticleName::fromString('Compresses stériles'),
            code: ArticleCode::fromString('COMPRESSE'),
            kind: ArticleKind::CONSUMABLE,
            gtin: null,
            taxCategory: TaxCategoryCode::fromString('veterinary.consumable.medical'),
            basePrice: CatalogPrice::excludingTax(Money::fromMinorUnits(1250, CurrencyCode::fromString('EUR'))),
            unitOfMeasure: UnitOfMeasure::fromString('BOX'),
            trackStock: true,
            createdAt: F::now(),
        );

        $mapper = new ArticleMapper();
        $entity = $mapper->toEntity($article);

        self::assertSame('excl', $entity->getBasePriceMode());

        $restored = $mapper->toDomain($entity);

        self::assertSame(1250, $restored->basePrice()->minorUnits());
        self::assertSame(PriceEntryMode::EXCLUDING_TAX, $restored->basePrice()->mode());
    }

    public function testAPackageKeepsTheModeOfItsFixedPriceAndNoneWithoutOne(): void
    {
        $mapper = new PackageMapper();

        $fixed = $mapper->toEntity(F::package(PackagePricingMode::FIXED_PRICE), []);
        self::assertSame('incl', $fixed->getFixedPriceMode());

        $restored = $mapper->toDomain($fixed, []);
        self::assertSame(25000, $restored->fixedPrice()?->minorUnits());
        self::assertSame(PriceEntryMode::INCLUDING_TAX, $restored->fixedPrice()->mode());

        $sum = $mapper->toEntity(F::package(PackagePricingMode::SUM_OF_COMPONENTS), [], $fixed);
        self::assertNull($sum->getFixedPriceMode());
        self::assertNull($mapper->toDomain($sum, [])->fixedPrice());
    }
}
