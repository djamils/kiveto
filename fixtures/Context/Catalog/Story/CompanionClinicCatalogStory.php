<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Catalog\Story;

use App\Context\Catalog\Domain\Act\ValueObject\ActStatus;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleKind;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleStatus;
use App\Context\Catalog\Domain\Package\ValueObject\PackagePricingMode;
use App\Context\Catalog\Domain\Package\ValueObject\PackageStatus;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListStatus;
use App\Fixtures\Context\Catalog\Factory\ActEntityFactory;
use App\Fixtures\Context\Catalog\Factory\ArticleEntityFactory;
use App\Fixtures\Context\Catalog\Factory\PackageEntityFactory;
use App\Fixtures\Context\Catalog\Factory\PriceListEntityFactory;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Story;

final class CompanionClinicCatalogStory extends Story
{
    // Demo clinic UUID — same as ClinicDataStory for consistency
    public const string CLINIC_ID = '01950000-0000-7000-0000-000000000001';

    public function build(): void
    {
        $clinicUuid = Uuid::fromString(self::CLINIC_ID);

        // Acts — active ones
        ActEntityFactory::createOne([
            'tenantId'                 => $clinicUuid,
            'code'                     => 'CONS_STD',
            'name'                     => 'Consultation standard',
            'category'                 => 'CONSULTATION',
            'taxCategoryCode'          => 'veterinary.act.consultation',
            'basePriceMinorUnits'      => 5000,
            'basePriceCurrency'        => 'EUR',
            'estimatedDurationMinutes' => 20,
            'requiresAnesthesia'       => false,
            'status'                   => ActStatus::ACTIVE,
        ]);

        ActEntityFactory::createOne([
            'tenantId'                 => $clinicUuid,
            'code'                     => 'STERIL_FEMELLE',
            'name'                     => 'Stérilisation femelle',
            'category'                 => 'SURGERY',
            'taxCategoryCode'          => 'veterinary.act.surgery',
            'basePriceMinorUnits'      => 25000,
            'basePriceCurrency'        => 'EUR',
            'estimatedDurationMinutes' => 90,
            'requiresAnesthesia'       => true,
            'status'                   => ActStatus::ACTIVE,
        ]);

        ActEntityFactory::createOne([
            'tenantId'                 => $clinicUuid,
            'code'                     => 'RADIO_THORAX',
            'name'                     => 'Radiographie thorax',
            'category'                 => 'IMAGING',
            'taxCategoryCode'          => 'veterinary.act.imaging',
            'basePriceMinorUnits'      => 8000,
            'basePriceCurrency'        => 'EUR',
            'estimatedDurationMinutes' => 15,
            'requiresAnesthesia'       => false,
            'status'                   => ActStatus::ACTIVE,
        ]);

        // Archived act
        ActEntityFactory::createOne([
            'tenantId'                 => $clinicUuid,
            'code'                     => 'OLD_CONS',
            'name'                     => 'Ancienne consultation',
            'category'                 => 'CONSULTATION',
            'taxCategoryCode'          => 'veterinary.act.consultation',
            'basePriceMinorUnits'      => 4000,
            'basePriceCurrency'        => 'EUR',
            'estimatedDurationMinutes' => 15,
            'requiresAnesthesia'       => false,
            'status'                   => ActStatus::ARCHIVED,
        ]);

        // Drug article
        ArticleEntityFactory::createOne([
            'tenantId'            => $clinicUuid,
            'code'                => 'AMOX-250',
            'name'                => 'Amoxicilline 250mg comprimés',
            'kind'                => ArticleKind::DRUG,
            'taxCategoryCode'     => 'veterinary.drug.prescription',
            'basePriceMinorUnits' => 1500,
            'basePriceCurrency'   => 'EUR',
            'unitOfMeasure'       => 'TABLET',
            'drugRequiresRx'      => true,
            'drugIsControlled'    => false,
            'trackStock'          => true,
            'status'              => ArticleStatus::ACTIVE,
        ]);

        // Non-drug articles
        ArticleEntityFactory::createOne([
            'tenantId'            => $clinicUuid,
            'code'                => 'COLLAR-E',
            'name'                => 'Collerette élisabéthaine',
            'kind'                => ArticleKind::CONSUMABLE,
            'taxCategoryCode'     => 'veterinary.consumable.standard',
            'basePriceMinorUnits' => 800,
            'basePriceCurrency'   => 'EUR',
            'unitOfMeasure'       => 'UNIT',
            'trackStock'          => true,
            'status'              => ArticleStatus::ACTIVE,
        ]);

        ArticleEntityFactory::createOne([
            'tenantId'            => $clinicUuid,
            'code'                => 'FOOD-RC',
            'name'                => 'Royal Canin Veterinary Diet',
            'kind'                => ArticleKind::FOOD,
            'taxCategoryCode'     => 'veterinary.food.dietetic',
            'basePriceMinorUnits' => 5500,
            'basePriceCurrency'   => 'EUR',
            'unitOfMeasure'       => 'KG',
            'trackStock'          => true,
            'status'              => ArticleStatus::ACTIVE,
        ]);

        // Package - fixed price
        PackageEntityFactory::createOne([
            'tenantId'             => $clinicUuid,
            'code'                 => 'PKG-STERIL-F',
            'name'                 => 'Pack stérilisation femelle complète',
            'taxCategoryCode'      => 'veterinary.act.surgery',
            'pricingMode'          => PackagePricingMode::FIXED_PRICE,
            'fixedPriceMinorUnits' => 38000,
            'fixedPriceCurrency'   => 'EUR',
            'status'               => PackageStatus::ACTIVE,
        ]);

        // Package - sum of components
        PackageEntityFactory::createOne([
            'tenantId'        => $clinicUuid,
            'code'            => 'PKG-BILAN',
            'name'            => 'Pack bilan annuel',
            'taxCategoryCode' => 'veterinary.act.consultation',
            'pricingMode'     => PackagePricingMode::SUM_OF_COMPONENTS,
            'status'          => PackageStatus::ACTIVE,
        ]);

        // Price list — default
        PriceListEntityFactory::createOne([
            'tenantId'  => $clinicUuid,
            'name'      => 'Tarifs standard',
            'isDefault' => true,
            'status'    => PriceListStatus::ACTIVE,
        ]);

        // Additional price list (non-default)
        PriceListEntityFactory::createOne([
            'tenantId'  => $clinicUuid,
            'name'      => 'Tarifs nuit et week-end',
            'isDefault' => false,
            'status'    => PriceListStatus::ACTIVE,
        ]);
    }
}
