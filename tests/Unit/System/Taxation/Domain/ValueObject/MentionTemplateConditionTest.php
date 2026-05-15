<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Taxation\Domain\ValueObject;

use App\Shared\Domain\ValueObject\CountryCode;
use App\System\Taxation\Domain\ValueObject\AnimalUsage;
use App\System\Taxation\Domain\ValueObject\ClinicLiabilityStatus;
use App\System\Taxation\Domain\ValueObject\CustomerTaxStatus;
use App\System\Taxation\Domain\ValueObject\FiscalContext;
use App\System\Taxation\Domain\ValueObject\MentionTemplateCondition;
use App\System\Taxation\Domain\ValueObject\RegionCode;
use PHPUnit\Framework\TestCase;

final class MentionTemplateConditionTest extends TestCase
{
    private \DateTimeImmutable $saleDate;

    protected function setUp(): void
    {
        $this->saleDate = new \DateTimeImmutable('2024-01-01');
    }

    public function testEmptyConditionMatchesAnyContext(): void
    {
        $condition = MentionTemplateCondition::of([], [], [], null);

        self::assertTrue($condition->matches($this->minimal()));
    }

    public function testRegionFilterMatchingRegion(): void
    {
        $context = FiscalContext::of(
            CountryCode::fromString('FR'),
            $this->saleDate,
            ClinicLiabilityStatus::LIABLE,
            RegionCode::fromString('DOM'),
        );
        $condition = MentionTemplateCondition::of([RegionCode::fromString('DOM')], [], [], null);

        self::assertTrue($condition->matches($context));
    }

    public function testRegionFilterNonMatchingRegion(): void
    {
        $context = FiscalContext::of(
            CountryCode::fromString('FR'),
            $this->saleDate,
            ClinicLiabilityStatus::LIABLE,
            RegionCode::fromString('IDF'),
        );
        $condition = MentionTemplateCondition::of([RegionCode::fromString('DOM')], [], [], null);

        self::assertFalse($condition->matches($context));
    }

    public function testCustomerStatusIntracomMatches(): void
    {
        $context = FiscalContext::of(
            CountryCode::fromString('FR'),
            $this->saleDate,
            ClinicLiabilityStatus::LIABLE,
            null,
            CustomerTaxStatus::INTRACOM,
        );
        $condition = MentionTemplateCondition::of([], [CustomerTaxStatus::INTRACOM], [], null);

        self::assertTrue($condition->matches($context));
    }

    public function testCustomerStatusB2cDoesNotMatchIntracomFilter(): void
    {
        $context = FiscalContext::of(
            CountryCode::fromString('FR'),
            $this->saleDate,
            ClinicLiabilityStatus::LIABLE,
            null,
            CustomerTaxStatus::B2C,
        );
        $condition = MentionTemplateCondition::of([], [CustomerTaxStatus::INTRACOM], [], null);

        self::assertFalse($condition->matches($context));
    }

    public function testAnimalUsageCompanionMatches(): void
    {
        $context = FiscalContext::of(
            CountryCode::fromString('FR'),
            $this->saleDate,
            ClinicLiabilityStatus::LIABLE,
            null,
            null,
            AnimalUsage::COMPANION,
        );
        $condition = MentionTemplateCondition::of([], [], [AnimalUsage::COMPANION], null);

        self::assertTrue($condition->matches($context));
    }

    public function testAnimalUsageLivestockDoesNotMatchCompanionFilter(): void
    {
        $context = FiscalContext::of(
            CountryCode::fromString('FR'),
            $this->saleDate,
            ClinicLiabilityStatus::LIABLE,
            null,
            null,
            AnimalUsage::LIVESTOCK,
        );
        $condition = MentionTemplateCondition::of([], [], [AnimalUsage::COMPANION], null);

        self::assertFalse($condition->matches($context));
    }

    public function testClinicLiabilityNotLiableMatches(): void
    {
        $context = FiscalContext::of(
            CountryCode::fromString('FR'),
            $this->saleDate,
            ClinicLiabilityStatus::NOT_LIABLE,
        );
        $condition = MentionTemplateCondition::of([], [], [], ClinicLiabilityStatus::NOT_LIABLE);

        self::assertTrue($condition->matches($context));
    }

    public function testClinicLiabilityLiableDoesNotMatchNotLiableFilter(): void
    {
        $context = FiscalContext::of(
            CountryCode::fromString('FR'),
            $this->saleDate,
            ClinicLiabilityStatus::LIABLE,
        );
        $condition = MentionTemplateCondition::of([], [], [], ClinicLiabilityStatus::NOT_LIABLE);

        self::assertFalse($condition->matches($context));
    }

    public function testCombinedRegionAndStatusBothMatch(): void
    {
        $context = FiscalContext::of(
            CountryCode::fromString('FR'),
            $this->saleDate,
            ClinicLiabilityStatus::LIABLE,
            RegionCode::fromString('DOM'),
            CustomerTaxStatus::INTRACOM,
        );
        $condition = MentionTemplateCondition::of(
            [RegionCode::fromString('DOM')],
            [CustomerTaxStatus::INTRACOM],
            [],
            null,
        );

        self::assertTrue($condition->matches($context));
    }

    public function testCombinedRegionAndStatusPartialMatch(): void
    {
        // region matches, status does not
        $context = FiscalContext::of(
            CountryCode::fromString('FR'),
            $this->saleDate,
            ClinicLiabilityStatus::LIABLE,
            RegionCode::fromString('DOM'),
            CustomerTaxStatus::B2C,
        );
        $condition = MentionTemplateCondition::of(
            [RegionCode::fromString('DOM')],
            [CustomerTaxStatus::INTRACOM],
            [],
            null,
        );

        self::assertFalse($condition->matches($context));
    }

    public function testNullContextFieldWithNonEmptyFilter(): void
    {
        // context has null animalUsage, filter requires COMPANION → false
        $context   = $this->minimal(); // animalUsage is null
        $condition = MentionTemplateCondition::of([], [], [AnimalUsage::COMPANION], null);

        self::assertFalse($condition->matches($context));
    }

    public function testNullRegionWithNonEmptyRegionFilterReturnsFalse(): void
    {
        // context has null region, filter requires DOM → false
        $context   = $this->minimal(); // region is null
        $condition = MentionTemplateCondition::of([RegionCode::fromString('DOM')], [], [], null);

        self::assertFalse($condition->matches($context));
    }

    public function testNullCustomerStatusWithNonEmptyStatusFilterReturnsFalse(): void
    {
        // context has null customerTaxStatus, filter requires INTRACOM → false
        $context   = $this->minimal(); // customerTaxStatus is null
        $condition = MentionTemplateCondition::of([], [CustomerTaxStatus::INTRACOM], [], null);

        self::assertFalse($condition->matches($context));
    }

    public function testGettersReturnConstructorValues(): void
    {
        $region    = RegionCode::fromString('DOM');
        $status    = CustomerTaxStatus::INTRACOM;
        $usage     = AnimalUsage::COMPANION;
        $liability = ClinicLiabilityStatus::NOT_LIABLE;

        $condition = MentionTemplateCondition::of([$region], [$status], [$usage], $liability);

        self::assertSame([$region], $condition->regionsMatching());
        self::assertSame([$status], $condition->customerStatusesMatching());
        self::assertSame([$usage], $condition->animalUsagesMatching());
        self::assertSame($liability, $condition->clinicLiability());
    }

    private function minimal(): FiscalContext
    {
        return FiscalContext::minimal(CountryCode::fromString('FR'), $this->saleDate);
    }
}
