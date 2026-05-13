<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Money\Application\Query;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\System\Money\Application\Query\ConvertMoney\ConvertMoney;
use App\System\Money\Application\Query\ConvertMoney\ConvertMoneyHandler;
use App\System\Money\Domain\Repository\ExchangeRateRepository;
use App\System\Money\Domain\RoundingPolicy\CommercialRounding;
use App\System\Money\Domain\Service\ConversionService;
use App\System\Money\Domain\Service\CurrencyRegistry;
use App\System\Money\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class ConvertMoneyHandlerTest extends TestCase
{
    public function testDelegatesToConversionService(): void
    {
        $date = new \DateTimeImmutable('2026-05-14');
        $eur  = CurrencyCode::fromString('EUR');

        // Use same-currency path (AC-05e) to avoid complex mock setup
        $exchangeRateRepository = $this->createMock(ExchangeRateRepository::class);
        $exchangeRateRepository->expects(self::never())->method('findRateAt');

        $currencyRegistry = $this->createStub(CurrencyRegistry::class);

        $conversionService = new ConversionService($exchangeRateRepository, $currencyRegistry);

        $handler = new ConvertMoneyHandler($conversionService);

        $amount   = Money::fromMinorUnits(1850, $eur);
        $rounding = new CommercialRounding();
        $query    = new ConvertMoney($amount, $eur, $date, $rounding);
        $result   = $handler($query);

        self::assertTrue($result->equals($amount));
    }
}
