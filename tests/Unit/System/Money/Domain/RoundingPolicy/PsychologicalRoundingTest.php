<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Money\Domain\RoundingPolicy;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\System\Money\Domain\RoundingPolicy\PsychologicalRounding;
use App\System\Money\Domain\ValueObject\CurrencyDecimals;
use App\System\Money\Domain\ValueObject\PsychologicalStrategy;
use PHPUnit\Framework\TestCase;

final class PsychologicalRoundingTest extends TestCase
{
    private CurrencyCode $eur;
    private CurrencyDecimals $decimals2;

    protected function setUp(): void
    {
        $this->eur       = CurrencyCode::fromString('EUR');
        $this->decimals2 = CurrencyDecimals::of(2);
    }

    public function testNinetyNine1947Gives1999(): void
    {
        // floor(19.47) + 0.99 = 19.99
        $rounding = new PsychologicalRounding(PsychologicalStrategy::NINETY_NINE);
        $result   = $rounding->round('19.47', $this->eur, $this->decimals2);

        self::assertSame('19.99', $result);
    }

    public function testNinetyNine1999Gives1999(): void
    {
        // floor(19.99) + 0.99 = 19.99
        $rounding = new PsychologicalRounding(PsychologicalStrategy::NINETY_NINE);
        $result   = $rounding->round('19.99', $this->eur, $this->decimals2);

        self::assertSame('19.99', $result);
    }

    public function testNinetyNine2000Gives2099(): void
    {
        // floor(20.00) + 0.99 = 20.99
        $rounding = new PsychologicalRounding(PsychologicalStrategy::NINETY_NINE);
        $result   = $rounding->round('20.00', $this->eur, $this->decimals2);

        self::assertSame('20.99', $result);
    }

    public function testNinety1947Gives1990(): void
    {
        // floor(19.47) + 0.90 = 19.90
        $rounding = new PsychologicalRounding(PsychologicalStrategy::NINETY);
        $result   = $rounding->round('19.47', $this->eur, $this->decimals2);

        self::assertSame('19.90', $result);
    }

    public function testNinety1990Gives1990(): void
    {
        // floor(19.90) + 0.90 = 19.90
        $rounding = new PsychologicalRounding(PsychologicalStrategy::NINETY);
        $result   = $rounding->round('19.90', $this->eur, $this->decimals2);

        self::assertSame('19.90', $result);
    }

    public function testNinety2000Gives2090(): void
    {
        // floor(20.00) + 0.90 = 20.90
        $rounding = new PsychologicalRounding(PsychologicalStrategy::NINETY);
        $result   = $rounding->round('20.00', $this->eur, $this->decimals2);

        self::assertSame('20.90', $result);
    }

    public function testTenCents1947Gives1950(): void
    {
        // ceil(19.47 / 0.10) * 0.10 = ceil(194.7) * 0.10 = 195 * 0.10 = 19.50
        $rounding = new PsychologicalRounding(PsychologicalStrategy::TEN_CENTS);
        $result   = $rounding->round('19.47', $this->eur, $this->decimals2);

        self::assertSame('19.50', $result);
    }

    public function testTenCents1950Gives1950(): void
    {
        // ceil(19.50 / 0.10) * 0.10 = ceil(195.0) * 0.10 = 195 * 0.10 = 19.50
        $rounding = new PsychologicalRounding(PsychologicalStrategy::TEN_CENTS);
        $result   = $rounding->round('19.50', $this->eur, $this->decimals2);

        self::assertSame('19.50', $result);
    }

    public function testTenCents1951Gives1960(): void
    {
        // ceil(19.51 / 0.10) * 0.10 = ceil(195.1) * 0.10 = 196 * 0.10 = 19.60
        $rounding = new PsychologicalRounding(PsychologicalStrategy::TEN_CENTS);
        $result   = $rounding->round('19.51', $this->eur, $this->decimals2);

        self::assertSame('19.60', $result);
    }
}
