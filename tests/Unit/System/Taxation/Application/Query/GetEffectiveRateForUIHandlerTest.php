<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Taxation\Application\Query;

use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\Service\CurrencyRegistry;
use App\Shared\Money\Domain\Service\MoneyCalculator;
use App\Shared\Money\Domain\Service\RoundingPolicyRegistry;
use App\Shared\Money\Domain\ValueObject\Currency;
use App\Shared\Money\Domain\ValueObject\CurrencyDecimals;
use App\Shared\Money\Domain\ValueObject\CurrencySymbol;
use App\System\Taxation\Application\Query\GetEffectiveRateForUI\GetEffectiveRateForUI;
use App\System\Taxation\Application\Query\GetEffectiveRateForUI\GetEffectiveRateForUIHandler;
use App\System\Taxation\Domain\Entity\TaxRate;
use App\System\Taxation\Domain\Repository\MentionTemplateRepositoryInterface;
use App\System\Taxation\Domain\Repository\TaxRegimeRepositoryInterface;
use App\System\Taxation\Domain\Service\MentionTemplateResolver;
use App\System\Taxation\Domain\Service\TaxCategoryRegistryInterface;
use App\System\Taxation\Domain\Service\TaxResolver;
use App\System\Taxation\Domain\TaxRegime;
use App\System\Taxation\Domain\ValueObject\FiscalContext;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use App\System\Taxation\Domain\ValueObject\TaxRateCondition;
use App\System\Taxation\Domain\ValueObject\TaxRateId;
use App\System\Taxation\Domain\ValueObject\TaxRateValue;
use App\System\Taxation\Domain\ValueObject\TaxRegimeId;
use App\System\Taxation\Domain\ValueObject\ValidityPeriod;
use PHPUnit\Framework\TestCase;

final class GetEffectiveRateForUIHandlerTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2024-06-01T10:00:00+00:00');
    }

    public function testDerivesEurForFrAndCallsResolver(): void
    {
        $regimeId     = TaxRegimeId::fromString('FR'); // EUR branch
        $categoryCode = TaxCategoryCode::fromString('veterinary.drug.companion');
        $context      = FiscalContext::minimal(CountryCode::fromString('FR'), $this->now);

        // Set up regime with super_reduced rate (5.5% = 550 bp) for drug.companion
        $superReducedRate = TaxRate::of(
            TaxRateId::fromString('fr.super_reduced'),
            TaxRateValue::ofBasisPoints(550),
            ValidityPeriod::openEndedFrom(new \DateTimeImmutable('2020-01-01')),
            TaxRateCondition::of(['veterinary.drug.companion'], [], [], [], null),
        );

        $regime = TaxRegime::reconstitute(
            $regimeId,
            'Test',
            CountryCode::fromString('FR'),
            [$superReducedRate],
            true,
            $this->now,
            $this->now,
        );

        $regimeRepo = $this->createStub(TaxRegimeRepositoryInterface::class);
        $regimeRepo->method('findById')->willReturn($regime);

        $categoryRegistry = $this->createStub(TaxCategoryRegistryInterface::class);
        $categoryRegistry->method('has')->willReturn(true);

        $resolver = $this->makeRealResolver($regimeRepo, $categoryRegistry);
        $handler  = new GetEffectiveRateForUIHandler($resolver);

        $result = $handler(new GetEffectiveRateForUI($categoryCode, $regimeId, $context));

        self::assertSame('5.50', $result->ratePercent);
        self::assertSame('FR', $result->regimeId);
    }

    public function testDerivesChfForChRegime(): void
    {
        $regimeId     = TaxRegimeId::fromString('CH'); // CHF branch
        $categoryCode = TaxCategoryCode::fromString('veterinary.act.consultation');
        $context      = FiscalContext::minimal(CountryCode::fromString('CH'), $this->now);

        $rate = TaxRate::of(
            TaxRateId::fromString('ch.standard'),
            TaxRateValue::ofBasisPoints(770),
            ValidityPeriod::openEndedFrom(new \DateTimeImmutable('2020-01-01')),
            TaxRateCondition::of([], [], [], [], null),
        );

        $regime = TaxRegime::reconstitute(
            $regimeId,
            'Test CH',
            CountryCode::fromString('CH'),
            [$rate],
            true,
            $this->now,
            $this->now,
        );

        $regimeRepo = $this->createStub(TaxRegimeRepositoryInterface::class);
        $regimeRepo->method('findById')->willReturn($regime);

        $categoryRegistry = $this->createStub(TaxCategoryRegistryInterface::class);
        $categoryRegistry->method('has')->willReturn(true);

        $resolver = $this->makeRealResolver($regimeRepo, $categoryRegistry);
        $handler  = new GetEffectiveRateForUIHandler($resolver);

        $result = $handler(new GetEffectiveRateForUI($categoryCode, $regimeId, $context));

        self::assertSame('7.70', $result->ratePercent);
        self::assertSame('CH', $result->regimeId);
    }

    public function testDerivesGbpForGbRegime(): void
    {
        $regimeId     = TaxRegimeId::fromString('GB'); // GBP branch
        $categoryCode = TaxCategoryCode::fromString('veterinary.act.consultation');
        $context      = FiscalContext::minimal(CountryCode::fromString('GB'), $this->now);

        $rate = TaxRate::of(
            TaxRateId::fromString('gb.standard'),
            TaxRateValue::ofBasisPoints(2000),
            ValidityPeriod::openEndedFrom(new \DateTimeImmutable('2020-01-01')),
            TaxRateCondition::of([], [], [], [], null),
        );

        $regime = TaxRegime::reconstitute(
            $regimeId,
            'Test GB',
            CountryCode::fromString('GB'),
            [$rate],
            true,
            $this->now,
            $this->now,
        );

        $regimeRepo = $this->createStub(TaxRegimeRepositoryInterface::class);
        $regimeRepo->method('findById')->willReturn($regime);

        $categoryRegistry = $this->createStub(TaxCategoryRegistryInterface::class);
        $categoryRegistry->method('has')->willReturn(true);

        $resolver = $this->makeRealResolver($regimeRepo, $categoryRegistry);
        $handler  = new GetEffectiveRateForUIHandler($resolver);

        $result = $handler(new GetEffectiveRateForUI($categoryCode, $regimeId, $context));

        self::assertSame('20.00', $result->ratePercent);
        self::assertSame('GB', $result->regimeId);
    }

    private function makeRealResolver(
        TaxRegimeRepositoryInterface $regimeRepo,
        TaxCategoryRegistryInterface $categoryRegistry,
    ): TaxResolver {
        $eur = CurrencyCode::fromString('EUR');

        $eurCurrency = Currency::of(
            code: $eur,
            symbol: CurrencySymbol::fromString('€'),
            decimals: CurrencyDecimals::of(2),
            displayName: 'Euro',
        );

        $currencyRegistry = $this->createStub(CurrencyRegistry::class);
        $currencyRegistry->method('get')->willReturn($eurCurrency);

        $mentionRepo = $this->createStub(MentionTemplateRepositoryInterface::class);
        $mentionRepo->method('findActiveByRegime')->willReturn([]);

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($this->now);

        return new TaxResolver(
            $regimeRepo,
            $categoryRegistry,
            new MoneyCalculator($currencyRegistry),
            new RoundingPolicyRegistry(),
            new MentionTemplateResolver($mentionRepo),
            $clock,
        );
    }
}
