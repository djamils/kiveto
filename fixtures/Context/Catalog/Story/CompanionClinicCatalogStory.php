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
use App\Fixtures\Context\Catalog\Factory\PriceListItemEntityFactory;
use App\Fixtures\Context\Clinic\Story\ClinicDataStory;
use App\Fixtures\System\PharmaceuticalRegistry\Factory\AuthorizationEntityFactory;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Story;

/**
 * Catalog for the demo companion-animal clinic (Paris, independent).
 *
 * Requires the PharmaceuticalRegistry stories (CoreMedicationsStory,
 * ControlledSubstancesStory, LivestockMedicationsStory) to be loaded first:
 * drug articles reference their marketing authorizations by commercial name.
 */
final class CompanionClinicCatalogStory extends Story
{
    public const string CLINIC_ID = ClinicDataStory::INDEPENDENT_CLINIC_ID;

    public function build(): void
    {
        $clinicUuid = Uuid::fromString(self::CLINIC_ID);

        // --- Acts (mix of categories, one archived) ---

        $actDefs = [
            ['code' => 'CONS_STD', 'name' => 'Consultation standard', 'category' => 'CONSULTATION', 'tax' => 'veterinary.act.consultation', 'price' => 5000, 'duration' => 20, 'anesthesia' => false, 'status' => ActStatus::ACTIVE],
            ['code' => 'VACC_CHPPIL', 'name' => 'Vaccination CHPPiL', 'category' => 'VACCINATION', 'tax' => 'veterinary.act.vaccination', 'price' => 6200, 'duration' => 15, 'anesthesia' => false, 'status' => ActStatus::ACTIVE],
            ['code' => 'STERIL_FEMELLE', 'name' => 'Stérilisation femelle', 'category' => 'SURGERY', 'tax' => 'veterinary.act.surgery', 'price' => 25000, 'duration' => 90, 'anesthesia' => true, 'status' => ActStatus::ACTIVE],
            ['code' => 'DETARTRAGE', 'name' => 'Détartrage complet', 'category' => 'SURGERY', 'tax' => 'veterinary.act.dental', 'price' => 12000, 'duration' => 45, 'anesthesia' => true, 'status' => ActStatus::ACTIVE],
            ['code' => 'RADIO_THORAX', 'name' => 'Radiographie thorax', 'category' => 'IMAGING', 'tax' => 'veterinary.act.imaging', 'price' => 8000, 'duration' => 15, 'anesthesia' => false, 'status' => ActStatus::ACTIVE],
            ['code' => 'BILAN_SANG', 'name' => 'Bilan sanguin complet', 'category' => 'LABORATORY', 'tax' => 'veterinary.act.laboratory', 'price' => 6500, 'duration' => 10, 'anesthesia' => false, 'status' => ActStatus::ACTIVE],
            ['code' => 'HOSP_JOUR', 'name' => 'Hospitalisation à la journée', 'category' => 'HOSPITALIZATION', 'tax' => 'veterinary.act.hospitalization', 'price' => 4500, 'duration' => 480, 'anesthesia' => false, 'status' => ActStatus::ACTIVE],
            ['code' => 'OLD_CONS', 'name' => 'Ancienne consultation', 'category' => 'CONSULTATION', 'tax' => 'veterinary.act.consultation', 'price' => 4000, 'duration' => 15, 'anesthesia' => false, 'status' => ActStatus::ARCHIVED],
        ];

        $acts = [];

        foreach ($actDefs as $def) {
            $acts[$def['code']] = ActEntityFactory::createOne([
                'tenantId'                 => $clinicUuid,
                'code'                     => $def['code'],
                'name'                     => $def['name'],
                'category'                 => $def['category'],
                'taxCategoryCode'          => $def['tax'],
                'basePriceMinorUnits'      => $def['price'],
                'basePriceCurrency'        => 'EUR',
                'estimatedDurationMinutes' => $def['duration'],
                'requiresAnesthesia'       => $def['anesthesia'],
                'status'                   => $def['status'],
            ]);
        }

        // --- Drug articles, each linked to its marketing authorization ---

        $drugDefs = [
            ['code' => 'APOQUEL-36', 'name' => 'Apoquel 3,6 mg comprimés', 'auth' => 'Apoquel 3,6 mg', 'rx' => true, 'controlled' => false, 'tax' => 'veterinary.drug.prescription', 'price' => 4890, 'unit' => 'TABLET', 'gtin' => '03661103046394', 'status' => ArticleStatus::ACTIVE],
            ['code' => 'CERENIA-10', 'name' => 'Cerenia 10 mg/mL solution injectable', 'auth' => 'Cerenia 10 mg/mL', 'rx' => true, 'controlled' => false, 'tax' => 'veterinary.drug.prescription', 'price' => 3550, 'unit' => 'VIAL', 'gtin' => '03661103046400', 'status' => ArticleStatus::ACTIVE],
            ['code' => 'CONVENIA-80', 'name' => 'Convenia 80 mg/mL solution injectable', 'auth' => 'Convenia 80 mg/mL', 'rx' => true, 'controlled' => false, 'tax' => 'veterinary.drug.prescription', 'price' => 9800, 'unit' => 'VIAL', 'gtin' => '03661103046424', 'status' => ArticleStatus::ACTIVE],
            ['code' => 'VETMEDIN-125', 'name' => 'Vetmedin 1,25 mg comprimés', 'auth' => 'Vetmedin 1,25 mg', 'rx' => true, 'controlled' => false, 'tax' => 'veterinary.drug.prescription', 'price' => 3250, 'unit' => 'TABLET', 'gtin' => '03661103046431', 'status' => ArticleStatus::ACTIVE],
            ['code' => 'SYNULOX-500', 'name' => 'Synulox 500 mg comprimés', 'auth' => 'Synulox 500 mg', 'rx' => true, 'controlled' => false, 'tax' => 'veterinary.drug.prescription', 'price' => 1500, 'unit' => 'TABLET', 'gtin' => '03661103046455', 'status' => ArticleStatus::ACTIVE],
            ['code' => 'MELOXIDYL-1', 'name' => 'Meloxidyl 1 mg/mL suspension buvable', 'auth' => 'Meloxidyl 1 mg/mL', 'rx' => true, 'controlled' => false, 'tax' => 'veterinary.drug.prescription', 'price' => 980, 'unit' => 'BOTTLE', 'gtin' => '03661103046479', 'status' => ArticleStatus::ACTIVE],
            ['code' => 'STRONGHOLD-45', 'name' => 'Stronghold 45 mg pipettes', 'auth' => 'Stronghold 45 mg', 'rx' => true, 'controlled' => false, 'tax' => 'veterinary.drug.prescription', 'price' => 2450, 'unit' => 'PIPETTE', 'gtin' => '03661103046448', 'status' => ArticleStatus::ACTIVE],
            ['code' => 'FRONTLINE-CB', 'name' => 'Frontline Combo Chien pipettes', 'auth' => 'Frontline Combo Chien', 'rx' => false, 'controlled' => false, 'tax' => 'veterinary.drug.companion', 'price' => 1890, 'unit' => 'PIPETTE', 'gtin' => '03661103046417', 'status' => ArticleStatus::ACTIVE],
            ['code' => 'CYSTAID-PLUS', 'name' => 'Cystaid Plus gélules', 'auth' => 'Cystaid Plus', 'rx' => false, 'controlled' => false, 'tax' => 'veterinary.drug.companion', 'price' => 2190, 'unit' => 'CAPSULE', 'gtin' => '03661103046462', 'status' => ArticleStatus::ACTIVE],
            ['code' => 'KETAMINE-100', 'name' => 'Ketamine 100 mg/mL solution injectable', 'auth' => 'Ketamine 100 mg/mL', 'rx' => true, 'controlled' => true, 'tax' => 'veterinary.drug.prescription', 'price' => 5600, 'unit' => 'VIAL', 'gtin' => null, 'status' => ArticleStatus::ACTIVE],
            ['code' => 'BAYTRIL-10', 'name' => 'Baytril 10% solution buvable', 'auth' => 'Baytril 10% Solution', 'rx' => true, 'controlled' => false, 'tax' => 'veterinary.drug.livestock', 'price' => 4200, 'unit' => 'BOTTLE', 'gtin' => null, 'status' => ArticleStatus::ACTIVE],
            ['code' => 'RILEXINE-300', 'name' => 'Rilexine 300 mg comprimés', 'auth' => 'Rilexine 300 mg', 'rx' => true, 'controlled' => false, 'tax' => 'veterinary.drug.prescription', 'price' => 1750, 'unit' => 'TABLET', 'gtin' => '03661103046486', 'status' => ArticleStatus::ARCHIVED],
        ];

        $articles = [];

        foreach ($drugDefs as $def) {
            $authorization = AuthorizationEntityFactory::repository()->findOneBy(['commercialName' => $def['auth']]);

            if (null === $authorization) {
                throw new \LogicException(\sprintf('Authorization "%s" must be loaded before the catalog story.', $def['auth']));
            }

            $articles[$def['code']] = ArticleEntityFactory::createOne([
                'tenantId'              => $clinicUuid,
                'code'                  => $def['code'],
                'name'                  => $def['name'],
                'kind'                  => ArticleKind::DRUG,
                'gtin'                  => $def['gtin'],
                'taxCategoryCode'       => $def['tax'],
                'basePriceMinorUnits'   => $def['price'],
                'basePriceCurrency'     => 'EUR',
                'unitOfMeasure'         => $def['unit'],
                'drugAuthRef'           => $authorization->getId(),
                'drugRequiresRx'        => $def['rx'],
                'drugPrescriptionClass' => $def['controlled'] ? 'RX_NARCOTIC' : ($def['rx'] ? 'RX' : 'NONE'),
                'drugIsControlled'      => $def['controlled'],
                'trackStock'            => true,
                'status'                => $def['status'],
            ]);
        }

        // --- Non-drug articles ---

        ArticleEntityFactory::createOne([
            'tenantId'            => $clinicUuid,
            'code'                => 'COLLAR-E',
            'name'                => 'Collerette élisabéthaine',
            'kind'                => ArticleKind::CONSUMABLE,
            'taxCategoryCode'     => 'veterinary.consumable.medical',
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
            'taxCategoryCode'     => 'veterinary.food.therapeutic',
            'basePriceMinorUnits' => 5500,
            'basePriceCurrency'   => 'EUR',
            'unitOfMeasure'       => 'KG',
            'trackStock'          => true,
            'status'              => ArticleStatus::ACTIVE,
        ]);

        // --- Packages ---

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

        PackageEntityFactory::createOne([
            'tenantId'        => $clinicUuid,
            'code'            => 'PKG-BILAN',
            'name'            => 'Pack bilan annuel',
            'taxCategoryCode' => 'veterinary.act.consultation',
            'pricingMode'     => PackagePricingMode::SUM_OF_COMPONENTS,
            'status'          => PackageStatus::ACTIVE,
        ]);

        // --- Price lists with item overrides ---

        $defaultList = PriceListEntityFactory::createOne([
            'tenantId'  => $clinicUuid,
            'name'      => 'Tarifs standard',
            'isDefault' => true,
            'status'    => PriceListStatus::ACTIVE,
        ]);

        $nightList = PriceListEntityFactory::createOne([
            'tenantId'  => $clinicUuid,
            'name'      => 'Tarifs nuit et week-end',
            'isDefault' => false,
            'status'    => PriceListStatus::ACTIVE,
        ]);

        $defaultItems = [
            ['type' => 'ACT', 'id' => $acts['CONS_STD']->getId(), 'price' => 4800],
            ['type' => 'ACT', 'id' => $acts['VACC_CHPPIL']->getId(), 'price' => 5900],
            ['type' => 'ARTICLE', 'id' => $articles['SYNULOX-500']->getId(), 'price' => 1350],
            ['type' => 'ARTICLE', 'id' => $articles['MELOXIDYL-1']->getId(), 'price' => 890],
        ];

        foreach ($defaultItems as $item) {
            PriceListItemEntityFactory::createOne([
                'priceListId'        => $defaultList->getId(),
                'itemType'           => $item['type'],
                'itemId'             => $item['id']->toRfc4122(),
                'netPriceMinorUnits' => $item['price'],
                'netPriceCurrency'   => 'EUR',
            ]);
        }

        // Night tariff override on the non-default list (exercises explicit
        // price-list resolution, see PriceResolver regression tests)
        PriceListItemEntityFactory::createOne([
            'priceListId'        => $nightList->getId(),
            'itemType'           => 'ACT',
            'itemId'             => $acts['CONS_STD']->getId()->toRfc4122(),
            'netPriceMinorUnits' => 7500,
            'netPriceCurrency'   => 'EUR',
        ]);
    }
}
