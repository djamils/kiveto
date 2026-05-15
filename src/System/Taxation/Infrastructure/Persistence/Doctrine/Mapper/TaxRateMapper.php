<?php

declare(strict_types=1);

namespace App\System\Taxation\Infrastructure\Persistence\Doctrine\Mapper;

use App\System\Taxation\Domain\Entity\TaxRate;
use App\System\Taxation\Domain\ValueObject\AnimalUsage;
use App\System\Taxation\Domain\ValueObject\ClinicLiabilityStatus;
use App\System\Taxation\Domain\ValueObject\CustomerTaxStatus;
use App\System\Taxation\Domain\ValueObject\RegionCode;
use App\System\Taxation\Domain\ValueObject\TaxRateCondition;
use App\System\Taxation\Domain\ValueObject\TaxRateId;
use App\System\Taxation\Domain\ValueObject\TaxRateValue;
use App\System\Taxation\Domain\ValueObject\ValidityPeriod;
use App\System\Taxation\Infrastructure\Persistence\Doctrine\Entity\TaxRateEntity;

final readonly class TaxRateMapper
{
    public function toDomain(TaxRateEntity $entity): TaxRate
    {
        $json = $entity->getConditionJson();

        /** @var list<string> $categoriesRaw */
        $categoriesRaw = $json['categories'] ?? [];

        /** @var list<string> $regionsRaw */
        $regionsRaw = $json['regions'] ?? [];

        /** @var list<string> $statusesRaw */
        $statusesRaw = $json['customer_statuses'] ?? [];

        /** @var list<string> $usagesRaw */
        $usagesRaw = $json['animal_usages'] ?? [];

        $regionsMatching  = array_map(static fn (string $r) => RegionCode::fromString($r), $regionsRaw);
        $customerStatuses = array_map(static fn (string $s) => CustomerTaxStatus::from($s), $statusesRaw);
        $animalUsages     = array_map(static fn (string $u) => AnimalUsage::from($u), $usagesRaw);

        /** @var string|null $clinicLiabilityRaw */
        $clinicLiabilityRaw = $json['clinic_liability'] ?? null;
        $clinicLiability    = null !== $clinicLiabilityRaw
            ? ClinicLiabilityStatus::from($clinicLiabilityRaw)
            : null;

        $condition = TaxRateCondition::of(
            $categoriesRaw,
            $regionsMatching,
            $customerStatuses,
            $animalUsages,
            $clinicLiability,
        );

        $validFrom = $entity->getValidFrom();
        $validTo   = $entity->getValidTo();
        $validity  = null === $validTo
            ? ValidityPeriod::openEndedFrom($validFrom)
            : ValidityPeriod::between($validFrom, $validTo);

        return TaxRate::of(
            TaxRateId::fromString($entity->getId()),
            TaxRateValue::ofBasisPoints($entity->getValueBp()),
            $validity,
            $condition,
        );
    }

    public function toEntity(TaxRate $rate, string $regimeId): TaxRateEntity
    {
        $condition     = $rate->appliesTo();
        $conditionJson = [
            'categories'        => $condition->categoriesMatching(),
            'regions'           => array_map(static fn (RegionCode $r) => $r->toString(), $condition->regionsMatching()),
            'customer_statuses' => array_map(static fn (CustomerTaxStatus $s) => $s->value, $condition->customerStatusesMatching()),
            'animal_usages'     => array_map(static fn (AnimalUsage $u) => $u->value, $condition->animalUsagesMatching()),
            'clinic_liability'  => $condition->clinicLiability()?->value,
        ];

        $entity = new TaxRateEntity();
        $entity->setId($rate->id()->toString());
        $entity->setRegimeId($regimeId);
        $entity->setValueBp($rate->value()->basisPoints());
        $entity->setValidFrom($rate->validityPeriod()->validFrom());
        $entity->setValidTo($rate->validityPeriod()->validTo());
        $entity->setConditionJson($conditionJson);
        $entity->setCreatedAt(new \DateTimeImmutable());

        return $entity;
    }
}
