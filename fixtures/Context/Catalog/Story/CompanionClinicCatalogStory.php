<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Catalog\Story;

use App\Context\Catalog\Domain\Act\ValueObject\ActStatus;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleKind;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleStatus;
use App\Context\Catalog\Domain\Package\ValueObject\PackagePricingMode;
use App\Context\Catalog\Domain\Package\ValueObject\PackageStatus;
use App\Context\Catalog\Domain\Pricing\ValueObject\PriceListStatus;
use App\Context\Catalog\Infrastructure\Persistence\Doctrine\Entity\ActEntity;
use App\Context\Catalog\Infrastructure\Persistence\Doctrine\Entity\ArticleEntity;
use App\Fixtures\Context\Catalog\Factory\ActEntityFactory;
use App\Fixtures\Context\Catalog\Factory\ArticleEntityFactory;
use App\Fixtures\Context\Catalog\Factory\PackageComponentEntityFactory;
use App\Fixtures\Context\Catalog\Factory\PackageEntityFactory;
use App\Fixtures\Context\Catalog\Factory\PriceListEntityFactory;
use App\Fixtures\Context\Catalog\Factory\PriceListItemEntityFactory;
use App\Fixtures\Context\Clinic\Story\ClinicDataStory;
use App\Fixtures\System\PharmaceuticalRegistry\Factory\AuthorizationEntityFactory;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Story;

/**
 * Catalog for both demo companion-animal clinics.
 *
 * The catalog is tenant-scoped and a tenant is a single clinic, so each clinic
 * needs its own rows: without them the consultation cockpit's act and drug
 * pickers come back empty. Both clinics share the same reference list — codes
 * are unique per tenant, not globally — and differ only by their price lists.
 *
 * Requires the PharmaceuticalRegistry stories (CoreMedicationsStory,
 * ControlledSubstancesStory, LivestockMedicationsStory) to be loaded first:
 * drug articles reference their marketing authorizations by commercial name.
 */
final class CompanionClinicCatalogStory extends Story
{
    public function build(): void
    {
        $this->buildCatalogFor(ClinicDataStory::INDEPENDENT_CLINIC_ID);
        $this->buildCatalogFor(ClinicDataStory::GROUP_CLINIC_ID);
        // The quiet clinic bills its morning bookings too (FluxDayStory).
        $this->buildCatalogFor(ClinicDataStory::CALM_CLINIC_ID);
    }

    private function buildCatalogFor(string $clinicId): void
    {
        $clinicUuid = Uuid::fromString($clinicId);

        $acts     = $this->createActs($clinicUuid);
        $articles = $this->createDrugArticles($clinicUuid);

        $consumables = $this->createNonDrugArticles($clinicUuid);
        $this->createPackages($clinicUuid, $acts, $articles + $consumables);
        $this->createPriceLists($clinicUuid, $acts, $articles);
    }

    /**
     * Every act category is represented, plus one archived act so the list
     * filters have something to hide.
     *
     * @return array<string, ActEntity>
     */
    private function createActs(Uuid $clinicUuid): array
    {
        $definitions = [
            ['code' => 'CONS_STD', 'desc' => 'Examen clinique général, conseils et prescription.', 'name' => 'Consultation standard', 'category' => 'CONSULTATION', 'tax' => 'veterinary.act.consultation', 'price' => 5000, 'duration' => 20, 'anesthesia' => false, 'status' => ActStatus::ACTIVE],
            ['code' => 'CONS_URGENCE', 'desc' => 'Sans rendez-vous ou hors créneaux, prise en charge immédiate.', 'name' => "Consultation d'urgence", 'category' => 'CONSULTATION', 'tax' => 'veterinary.act.emergency', 'price' => 9000, 'duration' => 30, 'anesthesia' => false, 'status' => ActStatus::ACTIVE],
            ['code' => 'CONS_NAC', 'desc' => 'Lapins, rongeurs, oiseaux et reptiles.', 'name' => 'Consultation NAC', 'category' => 'CONSULTATION', 'tax' => 'veterinary.act.consultation', 'price' => 5500, 'duration' => 25, 'anesthesia' => false, 'status' => ActStatus::ACTIVE],
            ['code' => 'CONS_SUIVI', 'desc' => 'Contrôle d\'un traitement, dans les 15 jours suivant une consultation.', 'name' => 'Consultation de suivi', 'category' => 'CONSULTATION', 'tax' => 'veterinary.act.consultation', 'price' => 3500, 'duration' => 15, 'anesthesia' => false, 'status' => ActStatus::ACTIVE],
            ['code' => 'VACC_CHPPIL', 'desc' => 'Carré, hépatite, parvovirose, parainfluenza et leptospirose.', 'name' => 'Vaccination CHPPiL', 'category' => 'VACCINATION', 'tax' => 'veterinary.act.vaccination', 'price' => 6200, 'duration' => 15, 'anesthesia' => false, 'status' => ActStatus::ACTIVE],
            ['code' => 'VACC_TYPHUS', 'desc' => 'Typhus, coryza (herpès et calicivirus).', 'name' => 'Vaccination typhus-coryza', 'category' => 'VACCINATION', 'tax' => 'veterinary.act.vaccination', 'price' => 5800, 'duration' => 15, 'anesthesia' => false, 'status' => ActStatus::ACTIVE],
            ['code' => 'VACC_RAGE', 'desc' => 'Vaccin, certificat et passeport européen.', 'name' => 'Vaccination antirabique', 'category' => 'VACCINATION', 'tax' => 'veterinary.act.vaccination', 'price' => 4500, 'duration' => 10, 'anesthesia' => false, 'status' => ActStatus::ACTIVE],
            ['code' => 'STERIL_FEMELLE', 'desc' => 'Ovariectomie sous anesthésie générale, contrôle à J+10 compris.', 'name' => 'Stérilisation femelle', 'category' => 'SURGERY', 'tax' => 'veterinary.act.surgery', 'price' => 25000, 'duration' => 90, 'anesthesia' => true, 'status' => ActStatus::ACTIVE],
            ['code' => 'CASTRATION_MALE', 'desc' => 'Orchiectomie sous anesthésie générale, contrôle post-opératoire compris.', 'name' => 'Castration mâle', 'category' => 'SURGERY', 'tax' => 'veterinary.act.surgery', 'price' => 15000, 'duration' => 45, 'anesthesia' => true, 'status' => ActStatus::ACTIVE],
            ['code' => 'SUTURE_PLAIE', 'desc' => 'Parage, suture et pansement ; anesthésie adaptée à l\'étendue de la plaie.', 'name' => 'Suture de plaie', 'category' => 'SURGERY', 'tax' => 'veterinary.act.surgery', 'price' => 11000, 'duration' => 40, 'anesthesia' => true, 'status' => ActStatus::ACTIVE],
            ['code' => 'DETARTRAGE', 'desc' => 'Ultrasons et polissage sous anesthésie générale.', 'name' => 'Détartrage complet', 'category' => 'SURGERY', 'tax' => 'veterinary.act.dental', 'price' => 12000, 'duration' => 45, 'anesthesia' => true, 'status' => ActStatus::ACTIVE],
            ['code' => 'EXTRACTION_DENT', 'desc' => 'Par dent, sous anesthésie générale ; se cumule avec le détartrage.', 'name' => 'Extraction dentaire', 'category' => 'SURGERY', 'tax' => 'veterinary.act.dental', 'price' => 7500, 'duration' => 30, 'anesthesia' => true, 'status' => ActStatus::ACTIVE],
            ['code' => 'RADIO_THORAX', 'desc' => 'Trois incidences, avec interprétation.', 'name' => 'Radiographie thorax', 'category' => 'IMAGING', 'tax' => 'veterinary.act.imaging', 'price' => 8000, 'duration' => 15, 'anesthesia' => false, 'status' => ActStatus::ACTIVE],
            ['code' => 'RADIO_MEMBRE', 'desc' => 'Deux incidences, avec interprétation.', 'name' => 'Radiographie de membre', 'category' => 'IMAGING', 'tax' => 'veterinary.act.imaging', 'price' => 7000, 'duration' => 15, 'anesthesia' => false, 'status' => ActStatus::ACTIVE],
            ['code' => 'ECHO_ABDO', 'desc' => 'Examen de l\'abdomen avec compte rendu et images.', 'name' => 'Échographie abdominale', 'category' => 'IMAGING', 'tax' => 'veterinary.act.imaging', 'price' => 9500, 'duration' => 30, 'anesthesia' => false, 'status' => ActStatus::ACTIVE],
            ['code' => 'BILAN_SANG', 'desc' => 'Numération formule et biochimie 10 paramètres.', 'name' => 'Bilan sanguin complet', 'category' => 'LABORATORY', 'tax' => 'veterinary.act.laboratory', 'price' => 6500, 'duration' => 10, 'anesthesia' => false, 'status' => ActStatus::ACTIVE],
            ['code' => 'ANALYSE_URINE', 'desc' => 'Bandelette, densité, culot et culture avec antibiogramme si positive.', 'name' => "Analyse d'urine (ECBU)", 'category' => 'LABORATORY', 'tax' => 'veterinary.act.laboratory', 'price' => 4200, 'duration' => 10, 'anesthesia' => false, 'status' => ActStatus::ACTIVE],
            ['code' => 'BILAN_RENAL', 'desc' => 'Urée, créatinine et SDMA, en interne ou envoyé au laboratoire.', 'name' => 'Bilan rénal (urée, créatinine, SDMA)', 'category' => 'LABORATORY', 'tax' => 'veterinary.act.laboratory', 'price' => 8800, 'duration' => 10, 'anesthesia' => false, 'status' => ActStatus::ACTIVE],
            ['code' => 'HOSP_JOUR', 'desc' => 'Surveillance, soins et repas sur la journée, hors médicaments et perfusion.', 'name' => 'Hospitalisation à la journée', 'category' => 'HOSPITALIZATION', 'tax' => 'veterinary.act.hospitalization', 'price' => 4500, 'duration' => 480, 'anesthesia' => false, 'status' => ActStatus::ACTIVE],
            ['code' => 'HOSP_NUIT', 'desc' => 'Surveillance de 19:00 à 08:00, hors médicaments et perfusion.', 'name' => 'Hospitalisation de nuit', 'category' => 'HOSPITALIZATION', 'tax' => 'veterinary.act.hospitalization', 'price' => 6800, 'duration' => 720, 'anesthesia' => false, 'status' => ActStatus::ACTIVE],
            ['code' => 'PERFUSION', 'desc' => 'Pose du cathéter et fluidothérapie sur 24 h.', 'name' => 'Pose de perfusion et fluidothérapie', 'category' => 'HOSPITALIZATION', 'tax' => 'veterinary.act.hospitalization', 'price' => 5200, 'duration' => 20, 'anesthesia' => false, 'status' => ActStatus::ACTIVE],
            ['code' => 'IDENT_PUCE', 'desc' => 'Pose de l\'insert et déclaration au fichier national I-CAD.', 'name' => 'Identification par puce électronique', 'category' => 'PREVENTION', 'tax' => 'veterinary.act.preventive', 'price' => 6500, 'duration' => 15, 'anesthesia' => false, 'status' => ActStatus::ACTIVE],
            ['code' => 'VERMIF', 'desc' => 'Vermifuge adapté au poids, administré pendant la consultation.', 'name' => 'Vermifugation', 'category' => 'PREVENTION', 'tax' => 'veterinary.act.preventive', 'price' => 2500, 'duration' => 10, 'anesthesia' => false, 'status' => ActStatus::ACTIVE],
            ['code' => 'BILAN_GERIA', 'desc' => 'Examen complet, tension artérielle, bilans sanguin et urinaire, pour les animaux de plus de 8 ans.', 'name' => 'Bilan gériatrique annuel', 'category' => 'PREVENTION', 'tax' => 'veterinary.act.preventive', 'price' => 12500, 'duration' => 45, 'anesthesia' => false, 'status' => ActStatus::ACTIVE],
            ['code' => 'CERTIF_SANTE', 'desc' => 'Examen clinique et certificat signé (voyage, vente, pension).', 'name' => 'Certificat de bonne santé', 'category' => 'OTHER', 'tax' => 'veterinary.act.consultation', 'price' => 3500, 'duration' => 15, 'anesthesia' => false, 'status' => ActStatus::ACTIVE],
            ['code' => 'EUTHANASIE', 'desc' => 'Sédation préalable, euthanasie et temps de recueillement avec la famille.', 'name' => 'Euthanasie et accompagnement', 'category' => 'OTHER', 'tax' => 'veterinary.act.consultation', 'price' => 9000, 'duration' => 30, 'anesthesia' => false, 'status' => ActStatus::ACTIVE],
            ['code' => 'OLD_CONS', 'desc' => 'Ancien tarif de consultation, remplacé par la consultation standard.', 'name' => 'Ancienne consultation', 'category' => 'CONSULTATION', 'tax' => 'veterinary.act.consultation', 'price' => 4000, 'duration' => 15, 'anesthesia' => false, 'status' => ActStatus::ARCHIVED],
        ];

        $acts = [];

        foreach ($definitions as $definition) {
            $acts[$definition['code']] = ActEntityFactory::createOne([
                'tenantId'                 => $clinicUuid,
                'code'                     => $definition['code'],
                'name'                     => $definition['name'],
                'description'              => $definition['desc'],
                'category'                 => $definition['category'],
                'taxCategoryCode'          => $definition['tax'],
                'basePriceMinorUnits'      => $definition['price'],
                'basePriceCurrency'        => 'EUR',
                'estimatedDurationMinutes' => $definition['duration'],
                'requiresAnesthesia'       => $definition['anesthesia'],
                'status'                   => $definition['status'],
            ]);
        }

        return $acts;
    }

    /**
     * Drug articles, each linked to its marketing authorization. Every
     * prescription class is represented so the cockpit's ordonnance badges and
     * the controlled-substance register both have something to show.
     *
     * @return array<string, ArticleEntity>
     */
    private function createDrugArticles(Uuid $clinicUuid): array
    {
        $definitions = [
            ['code' => 'APOQUEL-36', 'name' => 'Apoquel 3,6 mg comprimés', 'auth' => 'Apoquel 3,6 mg', 'class' => 'RX', 'tax' => 'veterinary.drug.prescription', 'price' => 4890, 'unit' => 'TABLET', 'gtin' => '03661103046394', 'status' => ArticleStatus::ACTIVE],
            ['code' => 'CERENIA-10', 'name' => 'Cerenia 10 mg/mL solution injectable', 'auth' => 'Cerenia 10 mg/mL', 'class' => 'RX', 'tax' => 'veterinary.drug.prescription', 'price' => 3550, 'unit' => 'VIAL', 'gtin' => '03661103046400', 'status' => ArticleStatus::ACTIVE],
            ['code' => 'CONVENIA-80', 'name' => 'Convenia 80 mg/mL solution injectable', 'auth' => 'Convenia 80 mg/mL', 'class' => 'RX', 'tax' => 'veterinary.drug.prescription', 'price' => 9800, 'unit' => 'VIAL', 'gtin' => '03661103046424', 'status' => ArticleStatus::ACTIVE],
            ['code' => 'VETMEDIN-125', 'name' => 'Vetmedin 1,25 mg comprimés', 'auth' => 'Vetmedin 1,25 mg', 'class' => 'RX', 'tax' => 'veterinary.drug.prescription', 'price' => 3250, 'unit' => 'TABLET', 'gtin' => '03661103046431', 'status' => ArticleStatus::ACTIVE],
            ['code' => 'SYNULOX-500', 'name' => 'Synulox 500 mg comprimés', 'auth' => 'Synulox 500 mg', 'class' => 'RX', 'tax' => 'veterinary.drug.prescription', 'price' => 1500, 'unit' => 'TABLET', 'gtin' => '03661103046455', 'status' => ArticleStatus::ACTIVE],
            ['code' => 'MELOXIDYL-1', 'name' => 'Meloxidyl 1 mg/mL suspension buvable', 'auth' => 'Meloxidyl 1 mg/mL', 'class' => 'RX', 'tax' => 'veterinary.drug.prescription', 'price' => 980, 'unit' => 'BOTTLE', 'gtin' => '03661103046479', 'status' => ArticleStatus::ACTIVE],
            ['code' => 'STRONGHOLD-45', 'name' => 'Stronghold 45 mg pipettes', 'auth' => 'Stronghold 45 mg', 'class' => 'RX', 'tax' => 'veterinary.drug.prescription', 'price' => 2450, 'unit' => 'PIPETTE', 'gtin' => '03661103046448', 'status' => ArticleStatus::ACTIVE],
            ['code' => 'FRONTLINE-CB', 'name' => 'Frontline Combo Chien pipettes', 'auth' => 'Frontline Combo Chien', 'class' => 'NONE', 'tax' => 'veterinary.drug.companion', 'price' => 1890, 'unit' => 'PIPETTE', 'gtin' => '03661103046417', 'status' => ArticleStatus::ACTIVE],
            ['code' => 'CYSTAID-PLUS', 'name' => 'Cystaid Plus gélules', 'auth' => 'Cystaid Plus', 'class' => 'NONE', 'tax' => 'veterinary.drug.companion', 'price' => 2190, 'unit' => 'CAPSULE', 'gtin' => '03661103046462', 'status' => ArticleStatus::ACTIVE],
            ['code' => 'KETAMINE-100', 'name' => 'Ketamine 100 mg/mL solution injectable', 'auth' => 'Ketamine 100 mg/mL', 'class' => 'RX_NARCOTIC', 'tax' => 'veterinary.drug.prescription', 'price' => 5600, 'unit' => 'VIAL', 'gtin' => null, 'status' => ArticleStatus::ACTIVE],
            ['code' => 'NARKETAN-10', 'name' => 'Narketan 10 mg/mL solution injectable', 'auth' => 'Narketan 10 mg/mL', 'class' => 'RX_NARCOTIC', 'tax' => 'veterinary.drug.prescription', 'price' => 5900, 'unit' => 'VIAL', 'gtin' => null, 'status' => ArticleStatus::ACTIVE],
            ['code' => 'MEDETO-1', 'name' => 'Medetomidine 1 mg/mL solution injectable', 'auth' => 'Medetomidine 1 mg/mL', 'class' => 'RX_CONTROLLED', 'tax' => 'veterinary.drug.prescription', 'price' => 6400, 'unit' => 'VIAL', 'gtin' => null, 'status' => ArticleStatus::ACTIVE],
            ['code' => 'BAYTRIL-10', 'name' => 'Baytril 10% solution buvable', 'auth' => 'Baytril 10% Solution', 'class' => 'RX', 'tax' => 'veterinary.drug.livestock', 'price' => 4200, 'unit' => 'BOTTLE', 'gtin' => null, 'status' => ArticleStatus::ACTIVE],
            ['code' => 'OXYTETRA-200', 'name' => 'Oxytetracycline 200 mg/mL solution injectable', 'auth' => 'Oxytetracycline 200 mg/mL', 'class' => 'RX', 'tax' => 'veterinary.drug.livestock', 'price' => 3800, 'unit' => 'VIAL', 'gtin' => null, 'status' => ArticleStatus::ACTIVE],
            ['code' => 'TERRAMYCIN-LA', 'name' => 'Terramycin LA solution injectable', 'auth' => 'Terramycin LA', 'class' => 'RX', 'tax' => 'veterinary.drug.livestock', 'price' => 4600, 'unit' => 'VIAL', 'gtin' => null, 'status' => ArticleStatus::ACTIVE],
            ['code' => 'RILEXINE-300', 'name' => 'Rilexine 300 mg comprimés', 'auth' => 'Rilexine 300 mg', 'class' => 'RX', 'tax' => 'veterinary.drug.prescription', 'price' => 1750, 'unit' => 'TABLET', 'gtin' => '03661103046486', 'status' => ArticleStatus::ARCHIVED],
        ];

        $articles = [];

        foreach ($definitions as $definition) {
            $authorization = AuthorizationEntityFactory::repository()->findOneBy(['commercialName' => $definition['auth']]);

            if (null === $authorization) {
                throw new \LogicException(\sprintf('Authorization "%s" must be loaded before the catalog story.', $definition['auth']));
            }

            $articles[$definition['code']] = ArticleEntityFactory::createOne([
                'tenantId'              => $clinicUuid,
                'code'                  => $definition['code'],
                'name'                  => $definition['name'],
                'kind'                  => ArticleKind::DRUG,
                'gtin'                  => $definition['gtin'],
                'taxCategoryCode'       => $definition['tax'],
                'basePriceMinorUnits'   => $definition['price'],
                'basePriceCurrency'     => 'EUR',
                'unitOfMeasure'         => $definition['unit'],
                'drugAuthRef'           => $authorization->getId(),
                'drugRequiresRx'        => 'NONE' !== $definition['class'],
                'drugPrescriptionClass' => $definition['class'],
                'drugIsControlled'      => \in_array($definition['class'], ['RX_CONTROLLED', 'RX_NARCOTIC'], true),
                'trackStock'            => true,
                'status'                => $definition['status'],
            ]);
        }

        return $articles;
    }

    /**
     * Consumables, therapeutic food and equipment — the three non-drug kinds a
     * practitioner bills alongside an act.
     *
     * @return array<string, ArticleEntity>
     */
    private function createNonDrugArticles(Uuid $clinicUuid): array
    {
        $definitions = [
            ['code' => 'COLLAR-E', 'name' => 'Collerette élisabéthaine', 'kind' => ArticleKind::CONSUMABLE, 'tax' => 'veterinary.consumable.medical', 'price' => 800, 'unit' => 'UNIT', 'status' => ArticleStatus::ACTIVE],
            ['code' => 'SERINGUE-5', 'name' => 'Seringue stérile 5 mL', 'kind' => ArticleKind::CONSUMABLE, 'tax' => 'veterinary.consumable.medical', 'price' => 60, 'unit' => 'UNIT', 'status' => ArticleStatus::ACTIVE],
            ['code' => 'CATH-22G', 'name' => 'Cathéter veineux 22G', 'kind' => ArticleKind::CONSUMABLE, 'tax' => 'veterinary.consumable.medical', 'price' => 320, 'unit' => 'UNIT', 'status' => ArticleStatus::ACTIVE],
            ['code' => 'FIL-SUT-30', 'name' => 'Fil de suture résorbable 3/0', 'kind' => ArticleKind::CONSUMABLE, 'tax' => 'veterinary.consumable.medical', 'price' => 750, 'unit' => 'UNIT', 'status' => ArticleStatus::ACTIVE],
            ['code' => 'COMPRESSE', 'name' => 'Compresses stériles (boîte de 50)', 'kind' => ArticleKind::CONSUMABLE, 'tax' => 'veterinary.consumable.medical', 'price' => 1250, 'unit' => 'BOX', 'status' => ArticleStatus::ACTIVE],
            ['code' => 'SHAMPOING-DERM', 'name' => 'Shampooing dermatologique', 'kind' => ArticleKind::CONSUMABLE, 'tax' => 'veterinary.consumable.hygiene', 'price' => 1690, 'unit' => 'BOTTLE', 'status' => ArticleStatus::ACTIVE],
            ['code' => 'FOOD-RC', 'name' => 'Royal Canin Veterinary Diet', 'kind' => ArticleKind::FOOD, 'tax' => 'veterinary.food.therapeutic', 'price' => 5500, 'unit' => 'KG', 'status' => ArticleStatus::ACTIVE],
            ['code' => 'FOOD-HILLS-KD', 'name' => "Hill's Prescription Diet k/d rénal", 'kind' => ArticleKind::FOOD, 'tax' => 'veterinary.food.therapeutic', 'price' => 4900, 'unit' => 'KG', 'status' => ArticleStatus::ACTIVE],
            ['code' => 'FOOD-JUNIOR', 'name' => 'Croquettes croissance chiot', 'kind' => ArticleKind::FOOD, 'tax' => 'veterinary.food.preventive', 'price' => 3800, 'unit' => 'KG', 'status' => ArticleStatus::ACTIVE],
            ['code' => 'MUSEL-T2', 'name' => 'Muselière panier taille 2', 'kind' => ArticleKind::EQUIPMENT, 'tax' => 'veterinary.consumable.hygiene', 'price' => 1450, 'unit' => 'UNIT', 'status' => ArticleStatus::ACTIVE],
            ['code' => 'HARNAIS-POST', 'name' => 'Harnais de soutien post-opératoire', 'kind' => ArticleKind::EQUIPMENT, 'tax' => 'veterinary.consumable.medical', 'price' => 4200, 'unit' => 'UNIT', 'status' => ArticleStatus::ACTIVE],
            ['code' => 'OLD-COLLAR', 'name' => 'Collerette souple (ancien modèle)', 'kind' => ArticleKind::CONSUMABLE, 'tax' => 'veterinary.consumable.medical', 'price' => 650, 'unit' => 'UNIT', 'status' => ArticleStatus::ARCHIVED],
        ];

        $created = [];

        foreach ($definitions as $definition) {
            $created[$definition['code']] = ArticleEntityFactory::createOne([
                'tenantId'            => $clinicUuid,
                'code'                => $definition['code'],
                'name'                => $definition['name'],
                'kind'                => $definition['kind'],
                'taxCategoryCode'     => $definition['tax'],
                'basePriceMinorUnits' => $definition['price'],
                'basePriceCurrency'   => 'EUR',
                'unitOfMeasure'       => $definition['unit'],
                'trackStock'          => ArticleKind::EQUIPMENT !== $definition['kind'],
                'status'              => $definition['status'],
            ]);
        }

        return $created;
    }

    /**
     * A forfait without its prestations is a price with nothing behind it, so
     * each demo package is composed — the catalogue page shows what it holds and
     * what it would cost at the counter.
     *
     * @param array<string, ActEntity>     $acts
     * @param array<string, ArticleEntity> $items
     */
    private function createPackages(Uuid $clinicUuid, array $acts, array $items): void
    {
        $sterilisation = PackageEntityFactory::createOne([
            'tenantId'             => $clinicUuid,
            'code'                 => 'PKG-STERIL-F',
            'name'                 => 'Pack stérilisation femelle complète',
            'taxCategoryCode'      => 'veterinary.act.surgery',
            'pricingMode'          => PackagePricingMode::FIXED_PRICE,
            'fixedPriceMinorUnits' => 38000,
            'fixedPriceCurrency'   => 'EUR',
            'status'               => PackageStatus::ACTIVE,
        ]);

        $this->compose($sterilisation->getId(), [
            ['type' => 'ACT', 'id' => $acts['STERIL_FEMELLE']->getId(), 'quantity' => 1],
            ['type' => 'ACT', 'id' => $acts['CONS_SUIVI']->getId(), 'quantity' => 1],
            ['type' => 'ARTICLE', 'id' => $items['MELOXIDYL-1']->getId(), 'quantity' => 1],
            ['type' => 'ARTICLE', 'id' => $items['COLLAR-E']->getId(), 'quantity' => 1],
            ['type' => 'ARTICLE', 'id' => $items['FIL-SUT-30']->getId(), 'quantity' => 2],
        ]);

        $puppy = PackageEntityFactory::createOne([
            'tenantId'             => $clinicUuid,
            'code'                 => 'PKG-CHIOT',
            'name'                 => 'Pack chiot première année',
            'taxCategoryCode'      => 'veterinary.act.preventive',
            'pricingMode'          => PackagePricingMode::FIXED_PRICE,
            'fixedPriceMinorUnits' => 16500,
            'fixedPriceCurrency'   => 'EUR',
            'status'               => PackageStatus::ACTIVE,
        ]);

        $this->compose($puppy->getId(), [
            ['type' => 'ACT', 'id' => $acts['CONS_STD']->getId(), 'quantity' => 2],
            ['type' => 'ACT', 'id' => $acts['VACC_CHPPIL']->getId(), 'quantity' => 2],
            ['type' => 'ACT', 'id' => $acts['VACC_RAGE']->getId(), 'quantity' => 1],
            ['type' => 'ARTICLE', 'id' => $items['FOOD-JUNIOR']->getId(), 'quantity' => 1],
        ]);

        // Sold at the sum of what it holds: the page has to add it up rather
        // than read a stored price.
        $checkup = PackageEntityFactory::createOne([
            'tenantId'        => $clinicUuid,
            'code'            => 'PKG-BILAN',
            'name'            => 'Pack bilan annuel',
            'taxCategoryCode' => 'veterinary.act.consultation',
            'pricingMode'     => PackagePricingMode::SUM_OF_COMPONENTS,
            'status'          => PackageStatus::ACTIVE,
        ]);

        $this->compose($checkup->getId(), [
            ['type' => 'ACT', 'id' => $acts['CONS_STD']->getId(), 'quantity' => 1],
            ['type' => 'ACT', 'id' => $acts['BILAN_SANG']->getId(), 'quantity' => 1],
            ['type' => 'ACT', 'id' => $acts['ANALYSE_URINE']->getId(), 'quantity' => 1],
        ]);

        $archived = PackageEntityFactory::createOne([
            'tenantId'        => $clinicUuid,
            'code'            => 'PKG-OLD-DENT',
            'name'            => 'Ancien pack dentaire',
            'taxCategoryCode' => 'veterinary.act.dental',
            'pricingMode'     => PackagePricingMode::SUM_OF_COMPONENTS,
            'status'          => PackageStatus::ARCHIVED,
        ]);

        $this->compose($archived->getId(), [
            ['type' => 'ACT', 'id' => $acts['DETARTRAGE']->getId(), 'quantity' => 1],
        ]);
    }

    /**
     * @param list<array{type: string, id: Uuid, quantity: int}> $components
     */
    private function compose(Uuid $packageId, array $components): void
    {
        foreach ($components as $weight => $component) {
            PackageComponentEntityFactory::createOne([
                'packageId' => $packageId,
                'itemType'  => $component['type'],
                'itemId'    => $component['id']->toRfc4122(),
                'quantity'  => $component['quantity'],
                'weight'    => $weight,
            ]);
        }
    }

    /**
     * @param array<string, ActEntity>     $acts
     * @param array<string, ArticleEntity> $articles
     */
    private function createPriceLists(Uuid $clinicUuid, array $acts, array $articles): void
    {
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
            ['type' => 'ACT', 'id' => $acts['BILAN_SANG']->getId(), 'price' => 6200],
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

        // Night tariff overrides on the non-default list (exercises explicit
        // price-list resolution, see PriceResolver regression tests)
        $nightItems = [
            ['type' => 'ACT', 'id' => $acts['CONS_STD']->getId(), 'price' => 7500],
            ['type' => 'ACT', 'id' => $acts['CONS_URGENCE']->getId(), 'price' => 13500],
        ];

        foreach ($nightItems as $item) {
            PriceListItemEntityFactory::createOne([
                'priceListId'        => $nightList->getId(),
                'itemType'           => $item['type'],
                'itemId'             => $item['id']->toRfc4122(),
                'netPriceMinorUnits' => $item['price'],
                'netPriceCurrency'   => 'EUR',
            ]);
        }
    }
}
