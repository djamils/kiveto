<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\ImportSources\France;

use App\System\PharmaceuticalRegistry\Domain\CompositionBlueprint;
use App\System\PharmaceuticalRegistry\Domain\Entity\TargetUsage;
use App\System\PharmaceuticalRegistry\Domain\Exception\UnknownAnmvCodeException;
use App\System\PharmaceuticalRegistry\Domain\MarketingAuthorizationBlueprint;
use App\System\PharmaceuticalRegistry\Domain\PresentationBlueprint;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\AdministrationRoute;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\AtcVetCode;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\CommercialName;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\FoodProductionPurpose;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\Gtin;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\HolderLaboratory;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ImportSource;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\JurisdictionalIdentifier;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\JurisdictionalPrescriptionCode;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\JurisdictionCode;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\MarketingAuthorizationDate;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\MarketingAuthorizationStatus;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\Packaging;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\PermanentIdentifier;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\PharmaceuticalForm;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\PrescriptionClass;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\PrescriptionRequirement;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\PresentationDescription;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ProductNature;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\TargetSpeciesCode;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\UnitCount;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\WithdrawalPeriod;
use App\System\PharmaceuticalRegistry\Infrastructure\ImportSources\France\Dto\AnmvCompositionDto;
use App\System\PharmaceuticalRegistry\Infrastructure\ImportSources\France\Dto\AnmvMedicinalProductDto;
use App\System\PharmaceuticalRegistry\Infrastructure\ImportSources\France\Dto\AnmvPresentationDto;
use App\System\PharmaceuticalRegistry\Infrastructure\ImportSources\France\Dto\AnmvVoieAdministrationDto;

final class AnmvCodeMapper
{
    /** @var array<int, string>|null */
    private ?array $speciesMapping = null;

    public function mapAuthorizationStatus(int $code): MarketingAuthorizationStatus
    {
        return match ($code) {
            1       => MarketingAuthorizationStatus::UNDER_REVIEW,
            2       => MarketingAuthorizationStatus::ACTIVE,
            3       => MarketingAuthorizationStatus::EXCEPTIONAL_CIRCUMSTANCES,
            4       => MarketingAuthorizationStatus::UNLIMITED,
            5       => MarketingAuthorizationStatus::WITHDRAWN,
            6       => MarketingAuthorizationStatus::WITHDRAWN_WITH_DEROGATION,
            7       => MarketingAuthorizationStatus::SUSPENDED,
            8       => MarketingAuthorizationStatus::REFUSED,
            9       => MarketingAuthorizationStatus::ABANDONED,
            10      => MarketingAuthorizationStatus::LAPSED,
            11      => MarketingAuthorizationStatus::ACTIVE,
            12      => MarketingAuthorizationStatus::WITHDRAWN,
            13      => MarketingAuthorizationStatus::SUSPENDED,
            14      => MarketingAuthorizationStatus::ACTIVE,
            15      => MarketingAuthorizationStatus::WITHDRAWN,
            16      => MarketingAuthorizationStatus::REFUSED,
            17      => MarketingAuthorizationStatus::ABANDONED,
            default => throw new UnknownAnmvCodeException($code),
        };
    }

    public function mapProductNature(int $code): ProductNature
    {
        return match ($code) {
            1       => ProductNature::CHEMICAL,
            2       => ProductNature::IMMUNOLOGICAL,
            3       => ProductNature::HOMEOPATHIC,
            default => throw new UnknownAnmvCodeException($code),
        };
    }

    public function mapAdministrationRoute(int $code): AdministrationRoute
    {
        return match ($code) {
            1       => AdministrationRoute::AURICULAR,
            2       => AdministrationRoute::CUTANEOUS,
            3       => AdministrationRoute::IN_OVO,
            4       => AdministrationRoute::INTRA_ARTICULAR,
            5       => AdministrationRoute::INTRACARDIAC,
            6       => AdministrationRoute::INTRADERMAL,
            7       => AdministrationRoute::INTRAMAMMARY,
            8       => AdministrationRoute::INTRAMUSCULAR,
            9       => AdministrationRoute::INTRANASAL,
            10      => AdministrationRoute::INTRAPERITONEAL,
            11      => AdministrationRoute::INTRARUMINAL,
            12      => AdministrationRoute::INTRAUTERINE,
            13      => AdministrationRoute::INTRAVENOUS,
            14      => AdministrationRoute::ORAL,
            15      => AdministrationRoute::OPHTHALMIC,
            16      => AdministrationRoute::OROMUCOSAL,
            17      => AdministrationRoute::RECTAL,
            18      => AdministrationRoute::SUBCUTANEOUS,
            19      => AdministrationRoute::TOPICAL,
            20      => AdministrationRoute::TRANSDERMAL,
            21      => AdministrationRoute::INHALATION,
            22      => AdministrationRoute::INTRAOSSEOUS,
            23      => AdministrationRoute::INTRATRACHEAL,
            24      => AdministrationRoute::INTRAVAGINAL,
            25      => AdministrationRoute::INTRAVESICAL,
            26      => AdministrationRoute::INTRACORONARY,
            27      => AdministrationRoute::INTRAPERICARDIAL,
            28      => AdministrationRoute::INTRAPLURAL,
            29      => AdministrationRoute::INTRASTERNAL,
            30      => AdministrationRoute::INTRACYSTIC,
            31      => AdministrationRoute::INTRACOLONIC,
            32      => AdministrationRoute::INTRAGASTRIC,
            33      => AdministrationRoute::INTROINTESTINAL,
            34      => AdministrationRoute::INTRALYMPHATIC,
            35      => AdministrationRoute::INTRATUMORAL,
            36      => AdministrationRoute::INTRAOCULAR,
            37      => AdministrationRoute::INTRABURSAL,
            38      => AdministrationRoute::EPIDURAL,
            39      => AdministrationRoute::PERINEURAL,
            40      => AdministrationRoute::PERIODONTAL,
            41      => AdministrationRoute::INTRASINUSAL,
            42      => AdministrationRoute::ENDOCERVICAL,
            43      => AdministrationRoute::ENDOSINUSAL,
            44      => AdministrationRoute::ENDOTRACHEAL,
            45      => AdministrationRoute::ENDOCARDIAL,
            46      => AdministrationRoute::INTRAABDOMINAL,
            47      => AdministrationRoute::INTRAHEPATIC,
            48      => AdministrationRoute::INTRARENAL,
            49      => AdministrationRoute::OTHER,
            default => throw new UnknownAnmvCodeException($code),
        };
    }

    public function mapFoodProductionPurpose(?int $code): ?FoodProductionPurpose
    {
        if (null === $code || 10 === $code) {
            return null;
        }

        return match ($code) {
            1       => FoodProductionPurpose::MUSCLE_SKIN,
            2       => FoodProductionPurpose::LIVER,
            3       => FoodProductionPurpose::KIDNEY,
            4       => FoodProductionPurpose::FAT,
            5       => FoodProductionPurpose::FAT_WITH_SKIN,
            6       => FoodProductionPurpose::EGGS,
            7       => FoodProductionPurpose::HONEY,
            8       => FoodProductionPurpose::MILK,
            9       => FoodProductionPurpose::ALL_FOOD_PRODUCTS,
            11      => FoodProductionPurpose::NOT_APPLICABLE,
            12      => FoodProductionPurpose::MUSCLE_SKIN,
            13      => FoodProductionPurpose::LIVER,
            14      => FoodProductionPurpose::NOT_APPLICABLE,
            default => throw new UnknownAnmvCodeException($code),
        };
    }

    public function mapPrescriptionRequirement(int $code): PrescriptionRequirement
    {
        $fr = JurisdictionCode::fromString('FR');

        return match ($code) {
            1, 2, 3 => PrescriptionRequirement::none(),
            4, 5, 6 => PrescriptionRequirement::rx(PrescriptionClass::RX, JurisdictionalPrescriptionCode::of($fr, (string) $code)),
            7, 8, 9, 10, 11, 12 => PrescriptionRequirement::rx(PrescriptionClass::RX_CONTROLLED, JurisdictionalPrescriptionCode::of($fr, (string) $code)),
            13, 14, 15, 16, 17 => PrescriptionRequirement::narcotic(JurisdictionalPrescriptionCode::of($fr, (string) $code)),
            18, 19, 20, 21, 22 => PrescriptionRequirement::rx(PrescriptionClass::RX, JurisdictionalPrescriptionCode::of($fr, (string) $code)),
            default => throw new UnknownAnmvCodeException($code),
        };
    }

    public function mapTargetSpecies(int $code): TargetSpeciesCode
    {
        $mapping = $this->loadSpeciesMapping();
        $slug    = $mapping[$code] ?? null;

        if (null === $slug) {
            throw new UnknownAnmvCodeException($code);
        }

        return TargetSpeciesCode::fromString($slug);
    }

    public function mapWithdrawalPeriod(?string $quantity, ?int $unitCode): ?WithdrawalPeriod
    {
        if (null === $quantity || null === $unitCode) {
            return null;
        }

        $value = (int) $quantity;

        if ($value <= 0) {
            return null;
        }

        return match ($unitCode) {
            1       => WithdrawalPeriod::days($value),
            2       => WithdrawalPeriod::hours($value),
            default => throw new UnknownAnmvCodeException($unitCode),
        };
    }

    public function buildBlueprint(AnmvMedicinalProductDto $dto): MarketingAuthorizationBlueprint
    {
        $presentations = array_map(
            fn (AnmvPresentationDto $p) => new PresentationBlueprint(
                description: PresentationDescription::fromString($p->description),
                gtin: null !== $p->gtin ? Gtin::fromString($p->gtin) : null,
                prescriptionRequirement: $this->mapPrescriptionRequirement($p->prescriptionCode),
                unitCount: null !== $p->unitCount ? UnitCount::fromString($p->unitCount) : null,
                packaging: null !== $p->packaging ? Packaging::fromString($p->packaging) : null,
                euPackIdentifier: null,
            ),
            $dto->presentations,
        );

        $compositions = array_map(
            static fn (AnmvCompositionDto $c) => new CompositionBlueprint(
                activeSubstanceLabel: $c->activeSubstanceLabel,
                quantityValue: $c->quantityValue,
                quantityUnitLabel: $c->quantityUnitLabel,
                quantityUnitCode: $c->quantityUnitCode,
                isExcipient: $c->isExcipient,
            ),
            $dto->compositions,
        );

        $targetUsages = array_map(
            fn (AnmvVoieAdministrationDto $v) => TargetUsage::of(
                administrationRoute: $this->mapAdministrationRoute($v->routeCode),
                targetSpeciesCode: $this->mapTargetSpecies($v->speciesCode),
                foodProductionPurpose: null !== $v->foodProductionCode ? $this->mapFoodProductionPurpose((int) $v->foodProductionCode) : null,
                withdrawalPeriod: $this->mapWithdrawalPeriod($v->withdrawalPeriodQuantity, $v->withdrawalPeriodUnitCode),
                jurisdictionalNote: $v->jurisdictionalNote,
            ),
            $dto->voiesAdministration,
        );

        $authDate = \DateTimeImmutable::createFromFormat('Y-m-d', $dto->authorizationDate);

        return new MarketingAuthorizationBlueprint(
            commercialName: CommercialName::fromString($dto->commercialName),
            holderLaboratory: HolderLaboratory::fromString($dto->holderLaboratory),
            status: $this->mapAuthorizationStatus($dto->statusCode),
            authorizationDate: MarketingAuthorizationDate::fromDateTime($authDate instanceof \DateTimeImmutable ? $authDate : new \DateTimeImmutable()),
            nature: $this->mapProductNature($dto->natureCode),
            pharmaceuticalForm: PharmaceuticalForm::fromString($dto->pharmaceuticalForm),
            atcVetCode: null !== $dto->atcVetCode ? AtcVetCode::fromString($dto->atcVetCode) : null,
            permanentIdentifier: null !== $dto->permanentIdentifier ? PermanentIdentifier::fromString($dto->permanentIdentifier) : null,
            controlledSubstance: null,
            jurisdictionalIdentifiers: [JurisdictionalIdentifier::anmv($dto->authorityIdentifier)],
            presentations: $presentations,
            compositions: $compositions,
            targetUsages: $targetUsages,
            summary: null,
            source: ImportSource::ANMV,
            contentHash: $dto->contentHash,
        );
    }

    public function getMappedStatusCount(): int
    {
        return 17;
    }

    public function getMappedRouteCount(): int
    {
        return 49;
    }

    public function getMappedPrescriptionCount(): int
    {
        return 22;
    }

    public function getMappedSpeciesCount(): int
    {
        return \count($this->loadSpeciesMapping());
    }

    /** @return array<int, string> */
    private function loadSpeciesMapping(): array
    {
        if (null === $this->speciesMapping) {
            /** @var array<int, string> $mapping */
            $mapping              = require __DIR__ . '/Resources/anmv-species-mapping.php';
            $this->speciesMapping = $mapping;
        }

        return $this->speciesMapping;
    }
}
