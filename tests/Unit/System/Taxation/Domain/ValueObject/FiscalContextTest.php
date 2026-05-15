<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Taxation\Domain\ValueObject;

use App\Shared\Domain\ValueObject\CountryCode;
use App\System\Taxation\Domain\ValueObject\AnimalUsage;
use App\System\Taxation\Domain\ValueObject\ClinicLiabilityStatus;
use App\System\Taxation\Domain\ValueObject\CustomerTaxStatus;
use App\System\Taxation\Domain\ValueObject\FiscalContext;
use App\System\Taxation\Domain\ValueObject\RegionCode;
use PHPUnit\Framework\TestCase;

final class FiscalContextTest extends TestCase
{
    public function testOfCreatesWithAllFields(): void
    {
        $country   = CountryCode::fromString('FR');
        $saleDate  = new \DateTimeImmutable('2024-06-01');
        $region    = RegionCode::fromString('DOM');
        $status    = CustomerTaxStatus::INTRACOM;
        $usage     = AnimalUsage::COMPANION;
        $liability = ClinicLiabilityStatus::NOT_LIABLE;

        $ctx = FiscalContext::of($country, $saleDate, $liability, $region, $status, $usage);

        self::assertSame('FR', $ctx->country()->toString());
        self::assertSame($saleDate, $ctx->saleDate());
        self::assertSame('DOM', $ctx->region()?->toString());
        self::assertSame(CustomerTaxStatus::INTRACOM, $ctx->customerTaxStatus());
        self::assertSame(AnimalUsage::COMPANION, $ctx->animalUsage());
        self::assertSame(ClinicLiabilityStatus::NOT_LIABLE, $ctx->clinicLiability());
    }

    public function testMinimalCreatesWithDefaultsLiable(): void
    {
        $country  = CountryCode::fromString('FR');
        $saleDate = new \DateTimeImmutable('2024-06-01');

        $ctx = FiscalContext::minimal($country, $saleDate);

        self::assertSame('FR', $ctx->country()->toString());
        self::assertSame($saleDate, $ctx->saleDate());
        self::assertNull($ctx->region());
        self::assertNull($ctx->customerTaxStatus());
        self::assertNull($ctx->animalUsage());
        self::assertSame(ClinicLiabilityStatus::LIABLE, $ctx->clinicLiability());
    }

    public function testAllGetters(): void
    {
        $country  = CountryCode::fromString('DE');
        $saleDate = new \DateTimeImmutable('2023-03-15');

        $ctx = FiscalContext::of($country, $saleDate);

        self::assertSame('DE', $ctx->country()->toString());
        self::assertSame($saleDate, $ctx->saleDate());
        self::assertNull($ctx->region());
        self::assertNull($ctx->customerTaxStatus());
        self::assertNull($ctx->animalUsage());
        self::assertSame(ClinicLiabilityStatus::LIABLE, $ctx->clinicLiability());
    }
}
