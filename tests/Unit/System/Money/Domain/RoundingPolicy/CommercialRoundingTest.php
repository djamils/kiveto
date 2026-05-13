<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Money\Domain\RoundingPolicy;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\System\Money\Domain\RoundingPolicy\CommercialRounding;
use App\System\Money\Domain\ValueObject\CurrencyDecimals;
use App\System\Money\Domain\ValueObject\RoundingPolicyId;
use PHPUnit\Framework\TestCase;

final class CommercialRoundingTest extends TestCase
{
    private CommercialRounding $rounding;
    private CurrencyCode $eur;
    private CurrencyDecimals $decimals2;

    protected function setUp(): void
    {
        $this->rounding  = new CommercialRounding();
        $this->eur       = CurrencyCode::fromString('EUR');
        $this->decimals2 = CurrencyDecimals::of(2);
    }

    public function testIdIsCommercial(): void
    {
        self::assertSame(RoundingPolicyId::COMMERCIAL, $this->rounding->id());
    }

    public function testRoundHalfUp1245Gives125(): void
    {
        // AC-04: HALF_AWAY_FROM_ZERO — "1.245" rounds to "1.25"
        $result = $this->rounding->round('1.245', $this->eur, $this->decimals2);

        self::assertSame('1.25', $result);
    }

    public function testRoundHalfUp1235Gives124(): void
    {
        // AC-04: HALF_AWAY_FROM_ZERO — "1.235" rounds to "1.24"
        // (note: PHP bcround HALF_AWAY_FROM_ZERO: .5 goes away from zero)
        $result = $this->rounding->round('1.235', $this->eur, $this->decimals2);

        self::assertSame('1.24', $result);
    }
}
