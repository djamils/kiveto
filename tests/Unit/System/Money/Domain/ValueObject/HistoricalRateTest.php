<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Money\Domain\ValueObject;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\System\Money\Domain\ValueObject\ExchangeRatePair;
use App\System\Money\Domain\ValueObject\HistoricalRate;
use PHPUnit\Framework\TestCase;

final class HistoricalRateTest extends TestCase
{
    public function testOfCreatesHistoricalRate(): void
    {
        $eur           = CurrencyCode::fromString('EUR');
        $chf           = CurrencyCode::fromString('CHF');
        $pair          = ExchangeRatePair::of($eur, $chf);
        $effectiveDate = new \DateTimeImmutable('2026-05-14');

        $historicalRate = HistoricalRate::of($pair, '0.9523', $effectiveDate, 'ECB');

        self::assertSame('EUR/CHF', $historicalRate->pair()->toString());
        self::assertSame('0.9523', $historicalRate->rate());
        self::assertSame('2026-05-14', $historicalRate->effectiveDate()->format('Y-m-d'));
        self::assertSame('ECB', $historicalRate->source());
    }
}
