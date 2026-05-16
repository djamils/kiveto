<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Taxation\Domain\Service;

use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\System\Money\Domain\Service\CurrencyRegistry;
use App\System\Money\Domain\Service\MoneyCalculator;
use App\System\Money\Domain\Service\RoundingPolicyRegistry;
use App\System\Money\Domain\ValueObject\Currency;
use App\System\Money\Domain\ValueObject\CurrencyDecimals;
use App\System\Money\Domain\ValueObject\CurrencySymbol;
use App\System\Money\Domain\ValueObject\Money;
use App\System\Taxation\Domain\Entity\TaxRate;
use App\System\Taxation\Domain\Exception\AmbiguousRateException;
use App\System\Taxation\Domain\Exception\NoApplicableRateException;
use App\System\Taxation\Domain\Exception\UnknownTaxCategoryException;
use App\System\Taxation\Domain\Exception\UnknownTaxRegimeException;
use App\System\Taxation\Domain\Repository\MentionTemplateRepositoryInterface;
use App\System\Taxation\Domain\Repository\TaxRegimeRepositoryInterface;
use App\System\Taxation\Domain\Service\MentionTemplateResolver;
use App\System\Taxation\Domain\Service\TaxCategoryRegistryInterface;
use App\System\Taxation\Domain\Service\TaxResolver;
use App\System\Taxation\Domain\TaxRegime;
use App\System\Taxation\Domain\ValueObject\FiscalContext;
use App\System\Taxation\Domain\ValueObject\TaxableItem;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use App\System\Taxation\Domain\ValueObject\TaxRateCondition;
use App\System\Taxation\Domain\ValueObject\TaxRateId;
use App\System\Taxation\Domain\ValueObject\TaxRateValue;
use App\System\Taxation\Domain\ValueObject\TaxRegimeId;
use App\System\Taxation\Domain\ValueObject\ValidityPeriod;
use PHPUnit\Framework\TestCase;

final class TaxResolverTest extends TestCase
{
    private \DateTimeImmutable $now;
    private \DateTimeImmutable $saleDate;
    private CurrencyCode $eur;
    private MoneyCalculator $calculator;
    private RoundingPolicyRegistry $roundingRegistry;

    protected function setUp(): void
    {
        $this->now      = new \DateTimeImmutable('2024-06-01T10:00:00+00:00');
        $this->saleDate = new \DateTimeImmutable('2024-06-01');
        $this->eur      = CurrencyCode::fromString('EUR');

        $eurCurrency = Currency::of(
            code: $this->eur,
            symbol: CurrencySymbol::fromString('€'),
            decimals: CurrencyDecimals::of(2),
            displayName: 'Euro',
        );

        $currencyRegistry = $this->createStub(CurrencyRegistry::class);
        $currencyRegistry->method('get')->willReturn($eurCurrency);

        $this->calculator       = new MoneyCalculator($currencyRegistry);
        $this->roundingRegistry = new RoundingPolicyRegistry();
    }

    public function testFrConsultationStandardRate(): void
    {
        $regimeId = TaxRegimeId::fromString('FR');
        $catCode  = TaxCategoryCode::fromString('veterinary.act.consultation');

        $standardRate = $this->makeRate('fr.standard', 2000); // 20%, empty condition matches all
        $regime       = $this->makeActiveRegime($regimeId, $standardRate);

        $regimeRepo = $this->createStub(TaxRegimeRepositoryInterface::class);
        $regimeRepo->method('findById')->willReturn($regime);

        $categoryRegistry = $this->createStub(TaxCategoryRegistryInterface::class);
        $categoryRegistry->method('has')->willReturn(true);

        $resolver = $this->makeResolver($regimeRepo, $categoryRegistry);

        $context = FiscalContext::minimal(CountryCode::fromString('FR'), $this->saleDate);
        $item    = TaxableItem::of(Money::zero($this->eur), $catCode);

        $application = $resolver->resolve($item, $regimeId, $context);

        self::assertSame(2000, $application->appliedRate()->value()->basisPoints());
    }

    public function testFrDrugCompanionSuperReducedRate(): void
    {
        $regimeId = TaxRegimeId::fromString('FR');
        $catCode  = TaxCategoryCode::fromString('veterinary.drug.companion');

        $standardRate     = $this->makeRate('fr.standard', 2000);
        $superReducedRate = $this->makeRate(
            'fr.super_reduced',
            550,
            TaxRateCondition::of(['veterinary.drug.companion'], [], [], [], null),
        );

        $regime = $this->makeActiveRegime($regimeId, $standardRate, $superReducedRate);

        $regimeRepo = $this->createStub(TaxRegimeRepositoryInterface::class);
        $regimeRepo->method('findById')->willReturn($regime);

        $categoryRegistry = $this->createStub(TaxCategoryRegistryInterface::class);
        $categoryRegistry->method('has')->willReturn(true);

        $resolver    = $this->makeResolver($regimeRepo, $categoryRegistry);
        $context     = FiscalContext::minimal(CountryCode::fromString('FR'), $this->saleDate);
        $item        = TaxableItem::of(Money::zero($this->eur), $catCode);
        $application = $resolver->resolve($item, $regimeId, $context);

        self::assertSame(550, $application->appliedRate()->value()->basisPoints());
    }

    public function testFrDrugLivestockParticularRate(): void
    {
        $regimeId = TaxRegimeId::fromString('FR');
        $catCode  = TaxCategoryCode::fromString('veterinary.drug.livestock');

        $standardRate   = $this->makeRate('fr.standard', 2000);
        $particularRate = $this->makeRate(
            'fr.particular',
            210,
            TaxRateCondition::of(['veterinary.drug.livestock'], [], [], [], null),
        );

        $regime = $this->makeActiveRegime($regimeId, $standardRate, $particularRate);

        $regimeRepo = $this->createStub(TaxRegimeRepositoryInterface::class);
        $regimeRepo->method('findById')->willReturn($regime);

        $categoryRegistry = $this->createStub(TaxCategoryRegistryInterface::class);
        $categoryRegistry->method('has')->willReturn(true);

        $resolver    = $this->makeResolver($regimeRepo, $categoryRegistry);
        $context     = FiscalContext::minimal(CountryCode::fromString('FR'), $this->saleDate);
        $item        = TaxableItem::of(Money::zero($this->eur), $catCode);
        $application = $resolver->resolve($item, $regimeId, $context);

        self::assertSame(210, $application->appliedRate()->value()->basisPoints());
    }

    public function testFrFoodTherapeuticSuperReducedRate(): void
    {
        $regimeId = TaxRegimeId::fromString('FR');
        $catCode  = TaxCategoryCode::fromString('veterinary.food.therapeutic');

        $standardRate     = $this->makeRate('fr.standard', 2000);
        $superReducedRate = $this->makeRate(
            'fr.super_reduced',
            550,
            TaxRateCondition::of(['veterinary.drug.companion', 'veterinary.food.therapeutic'], [], [], [], null),
        );

        $regime = $this->makeActiveRegime($regimeId, $standardRate, $superReducedRate);

        $regimeRepo = $this->createStub(TaxRegimeRepositoryInterface::class);
        $regimeRepo->method('findById')->willReturn($regime);

        $categoryRegistry = $this->createStub(TaxCategoryRegistryInterface::class);
        $categoryRegistry->method('has')->willReturn(true);

        $resolver    = $this->makeResolver($regimeRepo, $categoryRegistry);
        $context     = FiscalContext::minimal(CountryCode::fromString('FR'), $this->saleDate);
        $item        = TaxableItem::of(Money::zero($this->eur), $catCode);
        $application = $resolver->resolve($item, $regimeId, $context);

        self::assertSame(550, $application->appliedRate()->value()->basisPoints());
    }

    public function testUnknownRegimeThrows(): void
    {
        $regimeId = TaxRegimeId::fromString('FR');

        $regimeRepo = $this->createStub(TaxRegimeRepositoryInterface::class);
        $regimeRepo->method('findById')->willReturn(null);

        $categoryRegistry = $this->createStub(TaxCategoryRegistryInterface::class);

        $resolver = $this->makeResolver($regimeRepo, $categoryRegistry);

        $this->expectException(UnknownTaxRegimeException::class);

        $item = TaxableItem::of(Money::zero($this->eur), TaxCategoryCode::fromString('veterinary.act.consultation'));
        $ctx  = FiscalContext::minimal(CountryCode::fromString('FR'), $this->saleDate);

        $resolver->resolve($item, $regimeId, $ctx);
    }

    public function testInactiveRegimeThrows(): void
    {
        $regimeId = TaxRegimeId::fromString('FR');

        $inactiveRegime = TaxRegime::reconstitute(
            $regimeId,
            'Test',
            CountryCode::fromString('FR'),
            [],
            false,
            $this->now,
            $this->now,
        );

        $regimeRepo = $this->createStub(TaxRegimeRepositoryInterface::class);
        $regimeRepo->method('findById')->willReturn($inactiveRegime);

        $categoryRegistry = $this->createStub(TaxCategoryRegistryInterface::class);

        $resolver = $this->makeResolver($regimeRepo, $categoryRegistry);

        $this->expectException(UnknownTaxRegimeException::class);

        $item = TaxableItem::of(Money::zero($this->eur), TaxCategoryCode::fromString('veterinary.act.consultation'));
        $ctx  = FiscalContext::minimal(CountryCode::fromString('FR'), $this->saleDate);

        $resolver->resolve($item, $regimeId, $ctx);
    }

    public function testUnknownCategoryThrows(): void
    {
        $regimeId = TaxRegimeId::fromString('FR');
        $regime   = $this->makeActiveRegime($regimeId, $this->makeRate('fr.standard', 2000));

        $regimeRepo = $this->createStub(TaxRegimeRepositoryInterface::class);
        $regimeRepo->method('findById')->willReturn($regime);

        $categoryRegistry = $this->createStub(TaxCategoryRegistryInterface::class);
        $categoryRegistry->method('has')->willReturn(false);

        $resolver = $this->makeResolver($regimeRepo, $categoryRegistry);

        $this->expectException(UnknownTaxCategoryException::class);

        $item = TaxableItem::of(Money::zero($this->eur), TaxCategoryCode::fromString('veterinary.act.consultation'));
        $ctx  = FiscalContext::minimal(CountryCode::fromString('FR'), $this->saleDate);

        $resolver->resolve($item, $regimeId, $ctx);
    }

    public function testNoApplicableRateThrows(): void
    {
        $regimeId = TaxRegimeId::fromString('FR');

        // Rate expired in 2018
        $expiredRate = TaxRate::of(
            TaxRateId::fromString('fr.old'),
            TaxRateValue::ofBasisPoints(2000),
            ValidityPeriod::between(
                new \DateTimeImmutable('2014-01-01'),
                new \DateTimeImmutable('2018-12-31'),
            ),
            TaxRateCondition::of([], [], [], [], null),
        );

        $regime = $this->makeActiveRegime($regimeId, $expiredRate);

        $regimeRepo = $this->createStub(TaxRegimeRepositoryInterface::class);
        $regimeRepo->method('findById')->willReturn($regime);

        $categoryRegistry = $this->createStub(TaxCategoryRegistryInterface::class);
        $categoryRegistry->method('has')->willReturn(true);

        $resolver = $this->makeResolver($regimeRepo, $categoryRegistry);

        $this->expectException(NoApplicableRateException::class);

        $item = TaxableItem::of(Money::zero($this->eur), TaxCategoryCode::fromString('veterinary.act.consultation'));
        $ctx  = FiscalContext::minimal(CountryCode::fromString('FR'), $this->saleDate);

        $resolver->resolve($item, $regimeId, $ctx);
    }

    public function testAmbiguousRatesThrows(): void
    {
        $regimeId = TaxRegimeId::fromString('FR');
        $catCode  = TaxCategoryCode::fromString('veterinary.act.consultation');

        // Two rates, both exact match on veterinary.act.consultation → same score of 100
        $rateA = TaxRate::of(
            TaxRateId::fromString('fr.rate_a'),
            TaxRateValue::ofBasisPoints(2000),
            ValidityPeriod::openEndedFrom(new \DateTimeImmutable('2020-01-01')),
            TaxRateCondition::of(['veterinary.act.consultation'], [], [], [], null),
        );
        $rateB = TaxRate::of(
            TaxRateId::fromString('fr.rate_b'),
            TaxRateValue::ofBasisPoints(1500),
            ValidityPeriod::openEndedFrom(new \DateTimeImmutable('2020-01-01')),
            TaxRateCondition::of(['veterinary.act.consultation'], [], [], [], null),
        );

        $regime = $this->makeActiveRegime($regimeId, $rateA, $rateB);

        $regimeRepo = $this->createStub(TaxRegimeRepositoryInterface::class);
        $regimeRepo->method('findById')->willReturn($regime);

        $categoryRegistry = $this->createStub(TaxCategoryRegistryInterface::class);
        $categoryRegistry->method('has')->willReturn(true);

        $resolver = $this->makeResolver($regimeRepo, $categoryRegistry);

        $ctx  = FiscalContext::minimal(CountryCode::fromString('FR'), $this->saleDate);
        $item = TaxableItem::of(Money::zero($this->eur), $catCode);

        try {
            $resolver->resolve($item, $regimeId, $ctx);
            self::fail('Expected AmbiguousRateException was not thrown.');
        } catch (AmbiguousRateException $e) {
            self::assertMatchesRegularExpression('/fr\.rate_a.*fr\.rate_b|fr\.rate_b.*fr\.rate_a/', $e->getMessage());
        }
    }

    private function makeRate(string $id, int $basisPoints, ?TaxRateCondition $condition = null): TaxRate
    {
        return TaxRate::of(
            TaxRateId::fromString($id),
            TaxRateValue::ofBasisPoints($basisPoints),
            ValidityPeriod::openEndedFrom(new \DateTimeImmutable('2020-01-01')),
            $condition ?? TaxRateCondition::of([], [], [], [], null),
        );
    }

    private function makeActiveRegime(TaxRegimeId $regimeId, TaxRate ...$rates): TaxRegime
    {
        return TaxRegime::reconstitute(
            $regimeId,
            'Test Regime',
            CountryCode::fromString('FR'),
            array_values($rates),
            true,
            $this->now,
            $this->now,
        );
    }

    private function makeResolver(
        TaxRegimeRepositoryInterface $regimeRepo,
        TaxCategoryRegistryInterface $categoryRegistry,
    ): TaxResolver {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($this->now);

        $mentionRepo = $this->createStub(MentionTemplateRepositoryInterface::class);
        $mentionRepo->method('findActiveByRegime')->willReturn([]);

        $mentionResolver = new MentionTemplateResolver($mentionRepo);

        return new TaxResolver(
            $regimeRepo,
            $categoryRegistry,
            $this->calculator,
            $this->roundingRegistry,
            $mentionResolver,
            $clock,
        );
    }
}
