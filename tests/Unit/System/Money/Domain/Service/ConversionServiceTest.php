<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Money\Domain\Service;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\System\Money\Domain\Currency;
use App\System\Money\Domain\Exception\ExchangeRateNotFoundException;
use App\System\Money\Domain\Exception\StaleExchangeRateException;
use App\System\Money\Domain\ExchangeRate;
use App\System\Money\Domain\Repository\ExchangeRateRepository;
use App\System\Money\Domain\RoundingPolicy\CommercialRounding;
use App\System\Money\Domain\Service\ConversionService;
use App\System\Money\Domain\Service\CurrencyRegistry;
use App\System\Money\Domain\ValueObject\CurrencyDecimals;
use App\System\Money\Domain\ValueObject\CurrencySymbol;
use App\System\Money\Domain\ValueObject\ExchangeRateId;
use App\System\Money\Domain\ValueObject\ExchangeRatePair;
use App\System\Money\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class ConversionServiceTest extends TestCase
{
    private \DateTimeImmutable $now;
    private \DateTimeImmutable $requestedDate;
    private CurrencyCode $eur;
    private CurrencyCode $chf;

    protected function setUp(): void
    {
        $this->now           = new \DateTimeImmutable('2026-05-14T10:00:00+00:00');
        $this->requestedDate = new \DateTimeImmutable('2026-05-14');
        $this->eur           = CurrencyCode::fromString('EUR');
        $this->chf           = CurrencyCode::fromString('CHF');
    }

    public function testSameCurrencyShortCircuit(): void
    {
        // AC-05e: same currency returns immediately, no repository call
        $exchangeRateRepository = $this->createMock(ExchangeRateRepository::class);
        $exchangeRateRepository->expects(self::never())->method('findRateAt');

        $currencyRegistry = $this->createStub(CurrencyRegistry::class);

        $service = new ConversionService($exchangeRateRepository, $currencyRegistry);

        $amount = Money::fromMinorUnits(1850, $this->eur);
        $result = $service->convert($amount, $this->eur, $this->requestedDate, new CommercialRounding());

        self::assertTrue($result->equals($amount));
    }

    public function testConvertReturnsMoney(): void
    {
        $effectiveDate = new \DateTimeImmutable('2026-05-14');

        $mockRate = ExchangeRate::reconstitute(
            id: ExchangeRateId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            pair: ExchangeRatePair::of($this->eur, $this->chf),
            rate: '1.0000',
            effectiveDate: $effectiveDate,
            source: 'ECB',
            createdAt: $this->now,
        );

        $exchangeRateRepository = $this->createStub(ExchangeRateRepository::class);
        $exchangeRateRepository->method('findRateAt')->willReturn($mockRate);

        $eurCurrency = Currency::reconstitute(
            code: $this->eur,
            symbol: CurrencySymbol::fromString('€'),
            decimals: CurrencyDecimals::of(2),
            displayName: 'Euro',
            active: true,
            createdAt: $this->now,
            updatedAt: $this->now,
        );
        $chfCurrency = Currency::reconstitute(
            code: $this->chf,
            symbol: CurrencySymbol::fromString('CHF'),
            decimals: CurrencyDecimals::of(2),
            displayName: 'Swiss Franc',
            active: true,
            createdAt: $this->now,
            updatedAt: $this->now,
        );

        $currencyRegistry = $this->createStub(CurrencyRegistry::class);
        $currencyRegistry->method('get')->willReturnCallback(
            static function (CurrencyCode $code) use ($eurCurrency, $chfCurrency): Currency {
                if ('EUR' === $code->toString()) {
                    return $eurCurrency;
                }

                return $chfCurrency;
            },
        );

        $service = new ConversionService($exchangeRateRepository, $currencyRegistry);

        $amount = Money::fromMinorUnits(1850, $this->eur);
        $result = $service->convert($amount, $this->chf, $this->requestedDate, new CommercialRounding());

        self::assertSame('CHF', $result->currency()->toString());
        self::assertSame(1850, $result->minorUnits());
    }

    public function testThrowsExchangeRateNotFoundException(): void
    {
        // AC-06b
        $exchangeRateRepository = $this->createStub(ExchangeRateRepository::class);
        $exchangeRateRepository->method('findRateAt')->willReturn(null);

        $currencyRegistry = $this->createStub(CurrencyRegistry::class);

        $service = new ConversionService($exchangeRateRepository, $currencyRegistry);

        $amount = Money::fromMinorUnits(1850, $this->eur);

        $this->expectException(ExchangeRateNotFoundException::class);

        $service->convert($amount, $this->chf, $this->requestedDate, new CommercialRounding());
    }

    public function testThrowsStaleExchangeRateException(): void
    {
        // AC-06: rate is 8 days old, max is 7
        $requestedDate = new \DateTimeImmutable('2026-05-14');
        $staleDate     = new \DateTimeImmutable('2026-05-06'); // 8 days before

        $mockRate = ExchangeRate::reconstitute(
            id: ExchangeRateId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            pair: ExchangeRatePair::of($this->eur, $this->chf),
            rate: '0.9523',
            effectiveDate: $staleDate,
            source: 'ECB',
            createdAt: $this->now,
        );

        $exchangeRateRepository = $this->createStub(ExchangeRateRepository::class);
        $exchangeRateRepository->method('findRateAt')->willReturn($mockRate);

        $currencyRegistry = $this->createStub(CurrencyRegistry::class);

        $service = new ConversionService(
            $exchangeRateRepository,
            $currencyRegistry,
            maxStalenessInDays: 7,
        );

        $amount = Money::fromMinorUnits(1850, $this->eur);

        $this->expectException(StaleExchangeRateException::class);

        $service->convert($amount, $this->chf, $requestedDate, new CommercialRounding());
    }
}
