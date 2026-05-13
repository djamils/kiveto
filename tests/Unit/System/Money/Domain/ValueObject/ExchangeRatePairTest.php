<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Money\Domain\ValueObject;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\System\Money\Domain\ValueObject\ExchangeRatePair;
use PHPUnit\Framework\TestCase;

final class ExchangeRatePairTest extends TestCase
{
    public function testOfCreatesPair(): void
    {
        $eur  = CurrencyCode::fromString('EUR');
        $chf  = CurrencyCode::fromString('CHF');
        $pair = ExchangeRatePair::of($eur, $chf);

        self::assertSame('EUR', $pair->from()->toString());
        self::assertSame('CHF', $pair->to()->toString());
    }

    public function testOfThrowsWhenSameCurrency(): void
    {
        $eur = CurrencyCode::fromString('EUR');

        $this->expectException(\InvalidArgumentException::class);

        ExchangeRatePair::of($eur, $eur);
    }

    public function testInverse(): void
    {
        $eur     = CurrencyCode::fromString('EUR');
        $chf     = CurrencyCode::fromString('CHF');
        $pair    = ExchangeRatePair::of($eur, $chf);
        $inverse = $pair->inverse();

        self::assertSame('CHF', $inverse->from()->toString());
        self::assertSame('EUR', $inverse->to()->toString());
    }

    public function testToString(): void
    {
        $eur  = CurrencyCode::fromString('EUR');
        $chf  = CurrencyCode::fromString('CHF');
        $pair = ExchangeRatePair::of($eur, $chf);

        self::assertSame('EUR/CHF', $pair->toString());
    }

    public function testEquals(): void
    {
        $eur = CurrencyCode::fromString('EUR');
        $chf = CurrencyCode::fromString('CHF');
        $usd = CurrencyCode::fromString('USD');

        $a = ExchangeRatePair::of($eur, $chf);
        $b = ExchangeRatePair::of($eur, $chf);
        $c = ExchangeRatePair::of($eur, $usd);

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}
