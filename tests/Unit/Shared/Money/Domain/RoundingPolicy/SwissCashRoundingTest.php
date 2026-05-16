<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Money\Domain\RoundingPolicy;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\Exception\SwissCashRoundingRequiresChfException;
use App\Shared\Money\Domain\RoundingPolicy\SwissCashRounding;
use App\Shared\Money\Domain\ValueObject\CurrencyDecimals;
use App\Shared\Money\Domain\ValueObject\RoundingPolicyId;
use PHPUnit\Framework\TestCase;

final class SwissCashRoundingTest extends TestCase
{
    private SwissCashRounding $rounding;
    private CurrencyCode $chf;
    private CurrencyDecimals $decimals2;

    protected function setUp(): void
    {
        $this->rounding  = new SwissCashRounding();
        $this->chf       = CurrencyCode::fromString('CHF');
        $this->decimals2 = CurrencyDecimals::of(2);
    }

    public function testIdIsSwissCash(): void
    {
        self::assertSame(RoundingPolicyId::SWISS_CASH, $this->rounding->id());
    }

    public function testRoundNearestFiveCents1425Gives145(): void
    {
        // 1.425 / 0.05 = 28.5 → HALF_AWAY_FROM_ZERO → 29, 29 * 0.05 = 1.45
        $result = $this->rounding->round('1.425', $this->chf, $this->decimals2);

        self::assertSame('1.45', $result);
    }

    public function testRoundNearestFiveCents1410Gives140(): void
    {
        // 1.410 / 0.05 = 28.2 → rounds to 28, 28 * 0.05 = 1.40
        $result = $this->rounding->round('1.410', $this->chf, $this->decimals2);

        self::assertSame('1.40', $result);
    }

    public function testRoundNearestFiveCents1475Gives150(): void
    {
        // 1.475 / 0.05 = 29.5 → HALF_AWAY_FROM_ZERO → 30, 30 * 0.05 = 1.50
        $result = $this->rounding->round('1.475', $this->chf, $this->decimals2);

        self::assertSame('1.50', $result);
    }

    public function testRoundNearestFiveCents1440Gives145(): void
    {
        // 1.440 / 0.05 = 28.8 → rounds to 29, 29 * 0.05 = 1.45
        $result = $this->rounding->round('1.440', $this->chf, $this->decimals2);

        self::assertSame('1.45', $result);
    }

    public function testThrowsForNonChf(): void
    {
        // AC-03
        $eur = CurrencyCode::fromString('EUR');

        $this->expectException(SwissCashRoundingRequiresChfException::class);

        $this->rounding->round('1.425', $eur, $this->decimals2);
    }
}
