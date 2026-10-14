<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Catalog\Application\Command;

use App\Context\Catalog\Domain\Act\Act;
use App\Context\Catalog\Domain\Act\ValueObject\ActCategory;
use App\Context\Catalog\Domain\Act\ValueObject\ActCode;
use App\Context\Catalog\Domain\Act\ValueObject\ActDuration;
use App\Context\Catalog\Domain\Act\ValueObject\ActId;
use App\Context\Catalog\Domain\Act\ValueObject\ActName;
use App\Context\Catalog\Domain\Act\ValueObject\ActStatus;
use App\Context\Catalog\Domain\Article\Article;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleCode;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleId;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleKind;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleName;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleStatus;
use App\Context\Catalog\Domain\Article\ValueObject\DrugProperties;
use App\Context\Catalog\Domain\Article\ValueObject\MarketingAuthorizationRef;
use App\Context\Catalog\Domain\Article\ValueObject\PrescriptionClass;
use App\Context\Catalog\Domain\Package\Package;
use App\Context\Catalog\Domain\Package\PackageComponent;
use App\Context\Catalog\Domain\Package\ValueObject\PackageCode;
use App\Context\Catalog\Domain\Package\ValueObject\PackageId;
use App\Context\Catalog\Domain\Package\ValueObject\PackageName;
use App\Context\Catalog\Domain\Package\ValueObject\PackagePricingMode;
use App\Context\Catalog\Domain\Package\ValueObject\PackageStatus;
use App\Context\Catalog\Domain\Pricing\PriceList;
use App\Context\Catalog\Domain\Pricing\PriceListItem;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListId;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListName;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListStatus;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Context\Catalog\Infrastructure\Adapter\HealthRegistry\PharmaceuticalRef;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;

/**
 * Builds real Catalog aggregates for the command handler tests, so the
 * handlers are exercised against genuine domain behaviour instead of mocks.
 */
final class CatalogCommandFixtures
{
    public const string CLINIC_ID     = '01960000-0000-7000-8000-00000000c001';
    public const string ACT_ID        = '01960000-0000-7000-8000-00000000c002';
    public const string ARTICLE_ID    = '01960000-0000-7000-8000-00000000c003';
    public const string PACKAGE_ID    = '01960000-0000-7000-8000-00000000c004';
    public const string PRICE_LIST_ID = '01960000-0000-7000-8000-00000000c005';
    public const string GENERATED_ID  = '01960000-0000-7000-8000-00000000c006';

    private static ?\DateTimeImmutable $now = null;

    /** Always the same instance, so tests can compare timestamps by identity. */
    public static function now(): \DateTimeImmutable
    {
        return self::$now ??= new \DateTimeImmutable('2026-08-22 10:00:00');
    }

    public static function eur(int $minorUnits): Money
    {
        return Money::fromMinorUnits($minorUnits, CurrencyCode::fromString('EUR'));
    }

    public static function act(ActStatus $status = ActStatus::ACTIVE): Act
    {
        $createdAt = new \DateTimeImmutable('2026-01-01 10:00:00');

        return Act::reconstitute(
            id: ActId::fromString(self::ACT_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            name: ActName::fromString('Consultation standard'),
            code: ActCode::fromString('CONS-STD'),
            description: null,
            category: ActCategory::CONSULTATION,
            taxCategory: TaxCategoryCode::fromString('veterinary.act.consultation'),
            basePrice: self::eur(5000),
            estimatedDuration: ActDuration::ofMinutes(20),
            requiresAnesthesia: false,
            status: $status,
            createdAt: $createdAt,
            updatedAt: $createdAt,
        );
    }

    public static function consumable(ArticleStatus $status = ArticleStatus::ACTIVE): Article
    {
        $createdAt = new \DateTimeImmutable('2026-01-01 10:00:00');

        return Article::reconstitute(
            id: ArticleId::fromString(self::ARTICLE_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            name: ArticleName::fromString('Compresses stériles'),
            code: ArticleCode::fromString('COMP-01'),
            kind: ArticleKind::CONSUMABLE,
            gtin: null,
            taxCategory: TaxCategoryCode::fromString('veterinary.consumable'),
            basePrice: self::eur(350),
            unitOfMeasure: UnitOfMeasure::fromString('UNIT'),
            drugProperties: null,
            trackStock: true,
            status: $status,
            createdAt: $createdAt,
            updatedAt: $createdAt,
        );
    }

    public static function drug(ArticleStatus $status, ?string $authorizationRef): Article
    {
        $createdAt = new \DateTimeImmutable('2026-01-01 10:00:00');

        return Article::reconstitute(
            id: ArticleId::fromString(self::ARTICLE_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            name: ArticleName::fromString('Amoxicilline 250'),
            code: ArticleCode::fromString('AMOX-250'),
            kind: ArticleKind::DRUG,
            gtin: null,
            taxCategory: TaxCategoryCode::fromString('veterinary.drug.prescription'),
            basePrice: self::eur(1250),
            unitOfMeasure: UnitOfMeasure::fromString('UNIT'),
            drugProperties: DrugProperties::create(
                null !== $authorizationRef ? MarketingAuthorizationRef::fromString($authorizationRef) : null,
                true,
                PrescriptionClass::RX,
                false,
            ),
            trackStock: true,
            status: $status,
            createdAt: $createdAt,
            updatedAt: $createdAt,
        );
    }

    /** @param list<PackageComponent> $components */
    public static function package(
        PackagePricingMode $pricingMode = PackagePricingMode::FIXED_PRICE,
        PackageStatus $status = PackageStatus::ACTIVE,
        array $components = [],
    ): Package {
        $createdAt = new \DateTimeImmutable('2026-01-01 10:00:00');

        return Package::reconstitute(
            id: PackageId::fromString(self::PACKAGE_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            name: PackageName::fromString('Forfait stérilisation'),
            code: PackageCode::fromString('PKG-STER'),
            taxCategory: TaxCategoryCode::fromString('veterinary.act.surgery'),
            pricingMode: $pricingMode,
            fixedPrice: PackagePricingMode::FIXED_PRICE === $pricingMode ? self::eur(25000) : null,
            components: $components,
            status: $status,
            createdAt: $createdAt,
            updatedAt: $createdAt,
        );
    }

    /** @param list<PriceListItem> $items */
    public static function priceList(array $items = []): PriceList
    {
        $createdAt = new \DateTimeImmutable('2026-01-01 10:00:00');

        return PriceList::reconstitute(
            id: PriceListId::fromString(self::PRICE_LIST_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            name: PriceListName::fromString('Tarif standard'),
            isDefault: true,
            status: PriceListStatus::ACTIVE,
            items: $items,
            rules: [],
            createdAt: $createdAt,
            updatedAt: $createdAt,
        );
    }

    public static function pharmaceuticalRef(string $id, string $status): PharmaceuticalRef
    {
        return new PharmaceuticalRef(
            id: $id,
            commercialName: 'Amoxicilline 250',
            gtin: null,
            requiresPrescription: true,
            prescriptionClass: 'RX',
            isControlledSubstance: false,
            status: $status,
        );
    }
}
