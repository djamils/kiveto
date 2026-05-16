<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Taxation\Domain\ValueObject;

use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use App\Shared\Money\Domain\ValueObject\RoundingPolicyId;
use App\System\Taxation\Domain\ValueObject\AppliedTaxRate;
use App\System\Taxation\Domain\ValueObject\FiscalContext;
use App\System\Taxation\Domain\ValueObject\LegalMentions;
use App\System\Taxation\Domain\ValueObject\TaxApplication;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use App\System\Taxation\Domain\ValueObject\TaxRateId;
use App\System\Taxation\Domain\ValueObject\TaxRateValue;
use App\System\Taxation\Domain\ValueObject\TaxRegimeId;
use PHPUnit\Framework\TestCase;

final class TaxApplicationTest extends TestCase
{
    private CurrencyCode $eur;
    private TaxRegimeId $regimeId;
    private TaxCategoryCode $categoryCode;
    private AppliedTaxRate $appliedRate;
    private FiscalContext $fiscalContext;
    private \DateTimeImmutable $resolvedAt;

    protected function setUp(): void
    {
        $this->eur          = CurrencyCode::fromString('EUR');
        $this->regimeId     = TaxRegimeId::fromString('FR');
        $this->categoryCode = TaxCategoryCode::fromString('veterinary.act.consultation');
        $this->appliedRate  = AppliedTaxRate::of(
            TaxRateId::fromString('fr.standard'),
            $this->regimeId,
            TaxRateValue::ofBasisPoints(2000),
            new \DateTimeImmutable('2014-01-01'),
            'yaml.fr.standard',
        );
        $this->fiscalContext = FiscalContext::minimal(CountryCode::fromString('FR'), new \DateTimeImmutable('2026-05-16'));
        $this->resolvedAt    = new \DateTimeImmutable('2026-05-16T10:00:00+00:00');
    }

    public function testAllGettersReturnConstructorValues(): void
    {
        $net   = Money::fromMinorUnits(10000, $this->eur);
        $tax   = Money::fromMinorUnits(2000, $this->eur);
        $gross = Money::fromMinorUnits(12000, $this->eur);

        $application = TaxApplication::of(
            $this->regimeId,
            $this->categoryCode,
            $this->appliedRate,
            $net,
            $tax,
            $gross,
            RoundingPolicyId::ACCOUNTING,
            LegalMentions::empty(),
            $this->fiscalContext,
            $this->resolvedAt,
        );

        self::assertSame('FR', $application->taxRegimeId()->toString());
        self::assertSame('veterinary.act.consultation', $application->taxCategoryCode()->toString());
        self::assertSame($this->appliedRate, $application->appliedRate());
        self::assertSame(10000, $application->netAmount()->minorUnits());
        self::assertSame(2000, $application->taxAmount()->minorUnits());
        self::assertSame(12000, $application->grossAmount()->minorUnits());
        self::assertSame(RoundingPolicyId::ACCOUNTING, $application->roundingPolicyId());
        self::assertTrue($application->legalMentions()->isEmpty());
        self::assertSame($this->fiscalContext, $application->fiscalContext());
        self::assertSame($this->resolvedAt, $application->resolvedAt());
    }

    public function testCurrencyMismatchThrowsLogicException(): void
    {
        $chf = CurrencyCode::fromString('CHF');

        $this->expectException(\LogicException::class);

        TaxApplication::of(
            $this->regimeId,
            $this->categoryCode,
            $this->appliedRate,
            Money::fromMinorUnits(10000, $this->eur),
            Money::fromMinorUnits(2000, $chf),
            Money::fromMinorUnits(12000, $this->eur),
            RoundingPolicyId::ACCOUNTING,
            LegalMentions::empty(),
            $this->fiscalContext,
            $this->resolvedAt,
        );
    }

    public function testGrossMismatchThrowsLogicException(): void
    {
        $this->expectException(\LogicException::class);

        TaxApplication::of(
            $this->regimeId,
            $this->categoryCode,
            $this->appliedRate,
            Money::fromMinorUnits(10000, $this->eur),
            Money::fromMinorUnits(2000, $this->eur),
            Money::fromMinorUnits(13000, $this->eur), // wrong gross
            RoundingPolicyId::ACCOUNTING,
            LegalMentions::empty(),
            $this->fiscalContext,
            $this->resolvedAt,
        );
    }
}
