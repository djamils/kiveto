<?php

declare(strict_types=1);

namespace App\System\Taxation\Domain\ValueObject;

use App\Shared\Domain\ValueObject\CountryCode;

final class FiscalContext
{
    private function __construct(
        private readonly CountryCode $country,
        private readonly ?RegionCode $region,
        private readonly ?CustomerTaxStatus $customerTaxStatus,
        private readonly ?AnimalUsage $animalUsage,
        private readonly ClinicLiabilityStatus $clinicLiability,
        private readonly \DateTimeImmutable $saleDate,
    ) {
    }

    public static function of(
        CountryCode $country,
        \DateTimeImmutable $saleDate,
        ClinicLiabilityStatus $clinicLiability = ClinicLiabilityStatus::LIABLE,
        ?RegionCode $region = null,
        ?CustomerTaxStatus $customerTaxStatus = null,
        ?AnimalUsage $animalUsage = null,
    ): self {
        return new self($country, $region, $customerTaxStatus, $animalUsage, $clinicLiability, $saleDate);
    }

    public static function minimal(CountryCode $country, \DateTimeImmutable $saleDate): self
    {
        return new self($country, null, null, null, ClinicLiabilityStatus::LIABLE, $saleDate);
    }

    public function country(): CountryCode
    {
        return $this->country;
    }

    public function region(): ?RegionCode
    {
        return $this->region;
    }

    public function customerTaxStatus(): ?CustomerTaxStatus
    {
        return $this->customerTaxStatus;
    }

    public function animalUsage(): ?AnimalUsage
    {
        return $this->animalUsage;
    }

    public function clinicLiability(): ClinicLiabilityStatus
    {
        return $this->clinicLiability;
    }

    public function saleDate(): \DateTimeImmutable
    {
        return $this->saleDate;
    }
}
