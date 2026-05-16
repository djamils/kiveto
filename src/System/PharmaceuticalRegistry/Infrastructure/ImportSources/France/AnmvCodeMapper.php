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
            0 => MarketingAuthorizationStatus::UNDER_REVIEW,
            1 => MarketingAuthorizationStatus::ACTIVE,
            2 => MarketingAuthorizationStatus::REFUSED,
            3 => MarketingAuthorizationStatus::EXCEPTIONAL_CIRCUMSTANCES,
            4 => MarketingAuthorizationStatus::UNLIMITED,
            5 => MarketingAuthorizationStatus::ABANDONED,
            6 => MarketingAuthorizationStatus::WITHDRAWN_WITH_DEROGATION,
            7, 8, 9 => MarketingAuthorizationStatus::LAPSED,
            10 => MarketingAuthorizationStatus::WITHDRAWN,
            11 => MarketingAuthorizationStatus::SUSPENDED,
            13, 14 => MarketingAuthorizationStatus::UNDER_REVIEW,
            21      => MarketingAuthorizationStatus::ACTIVE,
            22      => MarketingAuthorizationStatus::ABANDONED,
            23      => MarketingAuthorizationStatus::UNDER_REVIEW,
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
            8       => AdministrationRoute::INTRAMAMMARY,
            9       => AdministrationRoute::INTRAMUSCULAR,
            10      => AdministrationRoute::INTRANASAL,
            11      => AdministrationRoute::INTRAPERITONEAL,
            12      => AdministrationRoute::INHALATION,
            13      => AdministrationRoute::OTHER,
            14      => AdministrationRoute::INTRARUMINAL,
            15      => AdministrationRoute::INTRAUTERINE,
            16      => AdministrationRoute::INTRAVENOUS,
            17      => AdministrationRoute::OPHTHALMIC,
            18      => AdministrationRoute::OTHER,
            19      => AdministrationRoute::ORAL,
            20      => AdministrationRoute::PERINEURAL,
            21      => AdministrationRoute::OTHER,
            22      => AdministrationRoute::PERINEURAL,
            23      => AdministrationRoute::OPHTHALMIC,
            24      => AdministrationRoute::SUBCUTANEOUS,
            25      => AdministrationRoute::OROMUCOSAL,
            26      => AdministrationRoute::OTHER,
            27      => AdministrationRoute::TRANSDERMAL,
            28      => AdministrationRoute::OTHER,
            29      => AdministrationRoute::TOPICAL,
            30      => AdministrationRoute::INTRAVAGINAL,
            537     => AdministrationRoute::OTHER,
            1239    => AdministrationRoute::INHALATION,
            1349    => AdministrationRoute::EPIDURAL,
            1980    => AdministrationRoute::INTRATUMORAL,
            4109    => AdministrationRoute::INTRANASAL,
            4110    => AdministrationRoute::OPHTHALMIC,
            4112    => AdministrationRoute::INTRAVENOUS,
            5871    => AdministrationRoute::OTHER,
            5872    => AdministrationRoute::OTHER,
            6703    => AdministrationRoute::OTHER,
            7088    => AdministrationRoute::OTHER,
            7455    => AdministrationRoute::INTRATUMORAL,
            9073    => AdministrationRoute::INTRA_ARTICULAR,
            9379    => AdministrationRoute::ENDOSINUSAL,
            9638    => AdministrationRoute::INHALATION,
            9704    => AdministrationRoute::ORAL,
            9705    => AdministrationRoute::ORAL,
            9949    => AdministrationRoute::ORAL,
            10432   => AdministrationRoute::INTRAMAMMARY,
            default => throw new UnknownAnmvCodeException($code),
        };
    }

    public function mapFoodProductionPurpose(?int $code): ?FoodProductionPurpose
    {
        if (null === $code) {
            return null;
        }

        return match ($code) {
            2  => FoodProductionPurpose::MUSCLE_SKIN,
            3  => FoodProductionPurpose::LIVER,
            4  => FoodProductionPurpose::KIDNEY,
            5  => FoodProductionPurpose::FAT,
            6  => FoodProductionPurpose::FAT_WITH_SKIN,
            7  => FoodProductionPurpose::EGGS,
            8  => FoodProductionPurpose::HONEY,
            9  => FoodProductionPurpose::MILK,
            11 => FoodProductionPurpose::ALL_FOOD_PRODUCTS,
            13, 14, 15, 16 => FoodProductionPurpose::MUSCLE_SKIN,
            default => throw new UnknownAnmvCodeException($code),
        };
    }

    public function mapPrescriptionRequirement(int $code): PrescriptionRequirement
    {
        $fr = JurisdictionCode::fromString('FR');

        return match ($code) {
            1, 2, 5, 6, 7, 8, 7093 => PrescriptionRequirement::none(),
            3, 4, 9, 11, 12, 14, 7094, 7095, 8565, 10930 => PrescriptionRequirement::rx(
                PrescriptionClass::RX,
                JurisdictionalPrescriptionCode::of($fr, (string) $code),
            ),
            10 => PrescriptionRequirement::rxWithRetention(
                PrescriptionClass::RX,
                JurisdictionalPrescriptionCode::of($fr, (string) $code),
                5,
            ),
            15, 389 => PrescriptionRequirement::rx(
                PrescriptionClass::RX_CONTROLLED,
                JurisdictionalPrescriptionCode::of($fr, (string) $code),
            ),
            13, 2440 => PrescriptionRequirement::narcotic(
                JurisdictionalPrescriptionCode::of($fr, (string) $code),
            ),
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
                foodProductionPurpose: $this->mapFoodProductionPurpose($v->foodProductionCode),
                withdrawalPeriod: $this->mapWithdrawalPeriod(
                    $v->withdrawalPeriodQuantity,
                    $v->withdrawalPeriodUnitCode,
                ),
                jurisdictionalNote: $v->jurisdictionalNote,
            ),
            $dto->voiesAdministration,
        );

        $authDate = \DateTimeImmutable::createFromFormat('Y-m-d', $dto->authorizationDate);

        return new MarketingAuthorizationBlueprint(
            commercialName: CommercialName::fromString($dto->commercialName),
            holderLaboratory: HolderLaboratory::fromString($dto->holderLaboratoryLabel),
            status: $this->mapAuthorizationStatus($dto->statusCode),
            authorizationDate: MarketingAuthorizationDate::fromDateTime(
                $authDate instanceof \DateTimeImmutable ? $authDate : new \DateTimeImmutable(),
            ),
            nature: $this->mapProductNature($dto->natureCode),
            pharmaceuticalForm: PharmaceuticalForm::fromString($dto->pharmaceuticalFormLabel),
            atcVetCode: null !== $dto->atcVetCode ? AtcVetCode::fromString($dto->atcVetCode) : null,
            permanentIdentifier: null !== $dto->permanentIdentifier
                ? PermanentIdentifier::fromString($dto->permanentIdentifier)
                : null,
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
