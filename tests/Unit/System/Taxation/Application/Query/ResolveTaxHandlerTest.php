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
use App\Shared\Money\Domain\ValueObject\Money;
use App\System\Taxation\Application\Query\ResolveTax\ResolveTax;
use App\System\Taxation\Application\Query\ResolveTax\ResolveTaxHandler;
use App\System\Taxation\Domain\Entity\TaxRate;
use App\System\Taxation\Domain\Exception\CurrencyMismatchForRegimeException;
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

final class ResolveTaxHandlerTest extends TestCase
{
    private \DateTimeImmutable $now;
    private CurrencyCode $eur;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2024-06-01T10:00:00+00:00');
        $this->eur = CurrencyCode::fromString('EUR');
    }

    public function testHappyPathReturnsTaxApplication(): void
    {
        $regimeId     = TaxRegimeId::fromString('FR');
        $categoryCode = TaxCategoryCode::fromString('veterinary.act.consultation');

        $rate = TaxRate::of(
            TaxRateId::fromString('fr.standard'),
            TaxRateValue::ofBasisPoints(2000),
            ValidityPeriod::openEndedFrom(new \DateTimeImmutable('2020-01-01')),
            TaxRateCondition::of([], [], [], [], null),
        );

        $regime = TaxRegime::reconstitute(
            $regimeId,
            'Test',
            CountryCode::fromString('FR'),
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
        $handler  = new ResolveTaxHandler($resolver);

        $netAmount = Money::zero($this->eur);
        $context   = FiscalContext::minimal(CountryCode::fromString('FR'), $this->now);

        $result = $handler(new ResolveTax($netAmount, $categoryCode, $regimeId, $context));

        self::assertSame(2000, $result->appliedRate()->value()->basisPoints());
        self::assertSame('FR', $result->taxRegimeId()->toString());
    }

    public function testCurrencyMismatchThrows(): void
    {
        $regimeId     = TaxRegimeId::fromString('FR'); // expects EUR
        $categoryCode = TaxCategoryCode::fromString('veterinary.act.consultation');
        $usdAmount    = Money::zero(CurrencyCode::fromString('USD'));
        $context      = FiscalContext::minimal(CountryCode::fromString('FR'), $this->now);

        $regimeRepo       = $this->createStub(TaxRegimeRepositoryInterface::class);
        $categoryRegistry = $this->createStub(TaxCategoryRegistryInterface::class);
        $resolver         = $this->makeRealResolver($regimeRepo, $categoryRegistry);
        $handler          = new ResolveTaxHandler($resolver);

        $this->expectException(CurrencyMismatchForRegimeException::class);

        $handler(new ResolveTax($usdAmount, $categoryCode, $regimeId, $context));
    }

    public function testChRegimeExpectsChfAndThrowsOnEurAmount(): void
    {
        $regimeId     = TaxRegimeId::fromString('CH'); // expects CHF
        $categoryCode = TaxCategoryCode::fromString('veterinary.act.consultation');
        $eurAmount    = Money::zero($this->eur);
        $context      = FiscalContext::minimal(CountryCode::fromString('CH'), $this->now);

        $regimeRepo       = $this->createStub(TaxRegimeRepositoryInterface::class);
        $categoryRegistry = $this->createStub(TaxCategoryRegistryInterface::class);
        $resolver         = $this->makeRealResolver($regimeRepo, $categoryRegistry);
        $handler          = new ResolveTaxHandler($resolver);

        $this->expectException(CurrencyMismatchForRegimeException::class);

        $handler(new ResolveTax($eurAmount, $categoryCode, $regimeId, $context));
    }

    public function testGbRegimeExpectsGbpAndThrowsOnEurAmount(): void
    {
        $regimeId     = TaxRegimeId::fromString('GB'); // expects GBP
        $categoryCode = TaxCategoryCode::fromString('veterinary.act.consultation');
        $eurAmount    = Money::zero($this->eur);
        $context      = FiscalContext::minimal(CountryCode::fromString('GB'), $this->now);

        $regimeRepo       = $this->createStub(TaxRegimeRepositoryInterface::class);
        $categoryRegistry = $this->createStub(TaxCategoryRegistryInterface::class);
        $resolver         = $this->makeRealResolver($regimeRepo, $categoryRegistry);
        $handler          = new ResolveTaxHandler($resolver);

        $this->expectException(CurrencyMismatchForRegimeException::class);

        $handler(new ResolveTax($eurAmount, $categoryCode, $regimeId, $context));
    }

    public function testDeRegimeExpectsEurAndAcceptsEurAmount(): void
    {
        $regimeId     = TaxRegimeId::fromString('DE'); // expects EUR (via match default branch for DE)
        $categoryCode = TaxCategoryCode::fromString('veterinary.act.consultation');

        $rate = TaxRate::of(
            TaxRateId::fromString('de.standard'),
            TaxRateValue::ofBasisPoints(1900),
            ValidityPeriod::openEndedFrom(new \DateTimeImmutable('2020-01-01')),
            TaxRateCondition::of([], [], [], [], null),
        );

        $regime = TaxRegime::reconstitute(
            $regimeId,
            'Test DE',
            CountryCode::fromString('DE'),
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
        $handler  = new ResolveTaxHandler($resolver);

        $netAmount = Money::zero($this->eur);
        $context   = FiscalContext::minimal(CountryCode::fromString('DE'), $this->now);

        $result = $handler(new ResolveTax($netAmount, $categoryCode, $regimeId, $context));

        self::assertSame(1900, $result->appliedRate()->value()->basisPoints());
        self::assertSame('DE', $result->taxRegimeId()->toString());
    }

    private function makeRealResolver(
        TaxRegimeRepositoryInterface $regimeRepo,
        TaxCategoryRegistryInterface $categoryRegistry,
    ): TaxResolver {
        $eurCurrency = Currency::of(
            code: $this->eur,
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
