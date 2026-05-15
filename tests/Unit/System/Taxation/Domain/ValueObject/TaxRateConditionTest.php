<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Taxation\Domain\ValueObject;

use App\Shared\Domain\ValueObject\CountryCode;
use App\System\Taxation\Domain\ValueObject\AnimalUsage;
use App\System\Taxation\Domain\ValueObject\ClinicLiabilityStatus;
use App\System\Taxation\Domain\ValueObject\CustomerTaxStatus;
use App\System\Taxation\Domain\ValueObject\FiscalContext;
use App\System\Taxation\Domain\ValueObject\RegionCode;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use App\System\Taxation\Domain\ValueObject\TaxRateCondition;
use PHPUnit\Framework\TestCase;

final class TaxRateConditionTest extends TestCase
{
    private FiscalContext $minimalContext;
    private TaxCategoryCode $consultation;
    private \DateTimeImmutable $saleDate;

    protected function setUp(): void
    {
        $this->saleDate       = new \DateTimeImmutable('2024-01-01');
        $this->minimalContext = FiscalContext::minimal(CountryCode::fromString('FR'), $this->saleDate);
        $this->consultation   = TaxCategoryCode::fromString('veterinary.act.consultation');
    }

    public function testEmptyConditionMatchesEverything(): void
    {
        $condition = TaxRateCondition::of([], [], [], [], null);

        self::assertTrue($condition->matches($this->consultation, $this->minimalContext));
    }

    public function testEmptyConditionScoreIsZero(): void
    {
        $condition = TaxRateCondition::of([], [], [], [], null);

        self::assertSame(0, $condition->specificityScore($this->consultation, $this->minimalContext));
    }

    public function testExactCategoryMatch(): void
    {
        $condition = TaxRateCondition::of(['veterinary.act.consultation'], [], [], [], null);

        self::assertTrue($condition->matches($this->consultation, $this->minimalContext));
        self::assertSame(100, $condition->specificityScore($this->consultation, $this->minimalContext));
    }

    public function testWildcardCategoryMatch(): void
    {
        $drugCompanion = TaxCategoryCode::fromString('veterinary.drug.companion');
        $condition     = TaxRateCondition::of(['veterinary.drug.*'], [], [], [], null);

        // "veterinary.drug.*" has non-wildcard segments: ["veterinary", "drug"] = 2 → score = 2 * 10 = 20
        self::assertTrue($condition->matches($drugCompanion, $this->minimalContext));
        self::assertSame(20, $condition->specificityScore($drugCompanion, $this->minimalContext));
    }

    public function testWildcardActCategoryMatch(): void
    {
        $condition = TaxRateCondition::of(['veterinary.act.*'], [], [], [], null);

        // "veterinary.act.*" has non-wildcard segments: ["veterinary", "act"] = 2 → score = 20
        self::assertTrue($condition->matches($this->consultation, $this->minimalContext));
        self::assertSame(20, $condition->specificityScore($this->consultation, $this->minimalContext));
    }

    public function testCategoryNoMatchReturnsFalse(): void
    {
        $condition = TaxRateCondition::of(['veterinary.drug.*'], [], [], [], null);

        self::assertFalse($condition->matches($this->consultation, $this->minimalContext));
    }

    public function testRegionFilterMatch(): void
    {
        $context = FiscalContext::of(
            CountryCode::fromString('FR'),
            $this->saleDate,
            ClinicLiabilityStatus::LIABLE,
            RegionCode::fromString('DOM'),
        );
        $condition = TaxRateCondition::of([], [RegionCode::fromString('DOM')], [], [], null);

        self::assertTrue($condition->matches($this->consultation, $context));
        self::assertSame(5, $condition->specificityScore($this->consultation, $context));
    }

    public function testRegionFilterNoMatchReturnsFalse(): void
    {
        $context = FiscalContext::of(
            CountryCode::fromString('FR'),
            $this->saleDate,
            ClinicLiabilityStatus::LIABLE,
            RegionCode::fromString('IDF'),
        );
        $condition = TaxRateCondition::of([], [RegionCode::fromString('DOM')], [], [], null);

        self::assertFalse($condition->matches($this->consultation, $context));
    }

    public function testCustomerStatusFilterMatch(): void
    {
        $context = FiscalContext::of(
            CountryCode::fromString('FR'),
            $this->saleDate,
            ClinicLiabilityStatus::LIABLE,
            null,
            CustomerTaxStatus::INTRACOM,
        );
        $condition = TaxRateCondition::of([], [], [CustomerTaxStatus::INTRACOM], [], null);

        self::assertTrue($condition->matches($this->consultation, $context));
        self::assertSame(4, $condition->specificityScore($this->consultation, $context));
    }

    public function testAnimalUsageFilterMatch(): void
    {
        $context = FiscalContext::of(
            CountryCode::fromString('FR'),
            $this->saleDate,
            ClinicLiabilityStatus::LIABLE,
            null,
            null,
            AnimalUsage::COMPANION,
        );
        $condition = TaxRateCondition::of([], [], [], [AnimalUsage::COMPANION], null);

        self::assertTrue($condition->matches($this->consultation, $context));
        self::assertSame(3, $condition->specificityScore($this->consultation, $context));
    }

    public function testClinicLiabilityFilterMatch(): void
    {
        $context = FiscalContext::of(
            CountryCode::fromString('FR'),
            $this->saleDate,
            ClinicLiabilityStatus::NOT_LIABLE,
        );
        $condition = TaxRateCondition::of([], [], [], [], ClinicLiabilityStatus::NOT_LIABLE);

        self::assertTrue($condition->matches($this->consultation, $context));
        self::assertSame(2, $condition->specificityScore($this->consultation, $context));
    }

    public function testMultiDimensionScore(): void
    {
        $drugCompanion = TaxCategoryCode::fromString('veterinary.drug.companion');
        $context       = FiscalContext::of(
            CountryCode::fromString('FR'),
            $this->saleDate,
            ClinicLiabilityStatus::LIABLE,
            RegionCode::fromString('DOM'),
            null,
            AnimalUsage::COMPANION,
        );
        // wildcard cat (veterinary.drug.*) → 20, region → +5, animal → +3 = 28
        $condition = TaxRateCondition::of(
            ['veterinary.drug.*'],
            [RegionCode::fromString('DOM')],
            [],
            [AnimalUsage::COMPANION],
            null,
        );

        self::assertTrue($condition->matches($drugCompanion, $context));
        self::assertSame(28, $condition->specificityScore($drugCompanion, $context));
    }

    public function testGettersReturnConstructorValues(): void
    {
        $region    = RegionCode::fromString('DOM');
        $status    = CustomerTaxStatus::INTRACOM;
        $usage     = AnimalUsage::COMPANION;
        $liability = ClinicLiabilityStatus::NOT_LIABLE;

        $condition = TaxRateCondition::of(
            ['veterinary.act.*'],
            [$region],
            [$status],
            [$usage],
            $liability,
        );

        self::assertSame(['veterinary.act.*'], $condition->categoriesMatching());
        self::assertSame([$region], $condition->regionsMatching());
        self::assertSame([$status], $condition->customerStatusesMatching());
        self::assertSame([$usage], $condition->animalUsagesMatching());
        self::assertSame($liability, $condition->clinicLiability());
    }

    public function testEqualsReturnsTrueForIdenticalConditions(): void
    {
        $a = TaxRateCondition::of(['veterinary.act.*'], [], [CustomerTaxStatus::B2B], [], null);
        $b = TaxRateCondition::of(['veterinary.act.*'], [], [CustomerTaxStatus::B2B], [], null);

        self::assertTrue($a->equals($b));
    }

    public function testEqualsReturnsFalseForDifferentCategories(): void
    {
        $a = TaxRateCondition::of(['veterinary.act.*'], [], [], [], null);
        $b = TaxRateCondition::of(['veterinary.drug.*'], [], [], [], null);

        self::assertFalse($a->equals($b));
    }

    public function testEqualsReturnsFalseForDifferentRegions(): void
    {
        $a = TaxRateCondition::of([], [RegionCode::fromString('DOM')], [], [], null);
        $b = TaxRateCondition::of([], [RegionCode::fromString('IDF')], [], [], null);

        self::assertFalse($a->equals($b));
    }

    public function testEqualsReturnsFalseForDifferentClinicLiability(): void
    {
        $a = TaxRateCondition::of([], [], [], [], ClinicLiabilityStatus::LIABLE);
        $b = TaxRateCondition::of([], [], [], [], null);

        self::assertFalse($a->equals($b));
    }

    public function testEqualsReturnsFalseForDifferentCustomerStatuses(): void
    {
        $a = TaxRateCondition::of([], [], [CustomerTaxStatus::INTRACOM], [], null);
        $b = TaxRateCondition::of([], [], [CustomerTaxStatus::B2B], [], null);

        self::assertFalse($a->equals($b));
    }

    public function testEqualsReturnsFalseForDifferentAnimalUsages(): void
    {
        $a = TaxRateCondition::of([], [], [], [AnimalUsage::COMPANION], null);
        $b = TaxRateCondition::of([], [], [], [AnimalUsage::LIVESTOCK], null);

        self::assertFalse($a->equals($b));
    }

    public function testSpecificityScoreIncludesCustomerStatusBonus(): void
    {
        $context = FiscalContext::of(
            CountryCode::fromString('FR'),
            $this->saleDate,
            ClinicLiabilityStatus::LIABLE,
            null,
            CustomerTaxStatus::B2B,
        );
        $condition = TaxRateCondition::of([], [], [CustomerTaxStatus::B2B], [], null);

        self::assertSame(4, $condition->specificityScore($this->consultation, $context));
    }

    public function testSpecificityScoreIncludesClinicLiabilityBonus(): void
    {
        $context = FiscalContext::of(
            CountryCode::fromString('FR'),
            $this->saleDate,
            ClinicLiabilityStatus::LIABLE,
        );
        $condition = TaxRateCondition::of([], [], [], [], ClinicLiabilityStatus::LIABLE);

        self::assertSame(2, $condition->specificityScore($this->consultation, $context));
    }

    public function testNullRegionWithRegionFilterReturnsFalse(): void
    {
        // context has null region, filter requires DOM → false (line 64)
        $context   = $this->minimalContext; // region is null
        $condition = TaxRateCondition::of([], [RegionCode::fromString('DOM')], [], [], null);

        self::assertFalse($condition->matches($this->consultation, $context));
    }

    public function testNullCustomerStatusWithStatusFilterReturnsFalse(): void
    {
        // context has null customerTaxStatus, filter requires INTRACOM → false (line 82)
        $context   = $this->minimalContext; // customerTaxStatus is null
        $condition = TaxRateCondition::of([], [], [CustomerTaxStatus::INTRACOM], [], null);

        self::assertFalse($condition->matches($this->consultation, $context));
    }

    public function testMismatchedCustomerStatusReturnsFalse(): void
    {
        // context has B2C, filter requires INTRACOM → false (line 85)
        $context = FiscalContext::of(
            CountryCode::fromString('FR'),
            $this->saleDate,
            ClinicLiabilityStatus::LIABLE,
            null,
            CustomerTaxStatus::B2C,
        );
        $condition = TaxRateCondition::of([], [], [CustomerTaxStatus::INTRACOM], [], null);

        self::assertFalse($condition->matches($this->consultation, $context));
    }

    public function testNullAnimalUsageWithUsageFilterReturnsFalse(): void
    {
        // context has null animalUsage, filter requires COMPANION → false (line 92)
        $context   = $this->minimalContext; // animalUsage is null
        $condition = TaxRateCondition::of([], [], [], [AnimalUsage::COMPANION], null);

        self::assertFalse($condition->matches($this->consultation, $context));
    }

    public function testMismatchedAnimalUsageReturnsFalse(): void
    {
        // context has LIVESTOCK, filter requires COMPANION → false (line 95)
        $context = FiscalContext::of(
            CountryCode::fromString('FR'),
            $this->saleDate,
            ClinicLiabilityStatus::LIABLE,
            null,
            null,
            AnimalUsage::LIVESTOCK,
        );
        $condition = TaxRateCondition::of([], [], [], [AnimalUsage::COMPANION], null);

        self::assertFalse($condition->matches($this->consultation, $context));
    }

    public function testMismatchedClinicLiabilityReturnsFalse(): void
    {
        // context has NOT_LIABLE, filter requires LIABLE → false (line 100)
        $context = FiscalContext::of(
            CountryCode::fromString('FR'),
            $this->saleDate,
            ClinicLiabilityStatus::NOT_LIABLE,
        );
        $condition = TaxRateCondition::of([], [], [], [], ClinicLiabilityStatus::LIABLE);

        self::assertFalse($condition->matches($this->consultation, $context));
    }
}
