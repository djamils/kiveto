<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Money\Domain\RoundingPolicy;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\System\Money\Domain\RoundingPolicy\AccountingRounding;
use App\System\Money\Domain\ValueObject\CurrencyDecimals;
use App\System\Money\Domain\ValueObject\RoundingPolicyId;
use PHPUnit\Framework\TestCase;

final class AccountingRoundingTest extends TestCase
{
    private AccountingRounding $rounding;
    private CurrencyCode $eur;
    private CurrencyDecimals $decimals2;

    protected function setUp(): void
    {
        $this->rounding  = new AccountingRounding();
        $this->eur       = CurrencyCode::fromString('EUR');
        $this->decimals2 = CurrencyDecimals::of(2);
    }

    public function testIdIsAccounting(): void
    {
        self::assertSame(RoundingPolicyId::ACCOUNTING, $this->rounding->id());
    }

    public function testRoundHalfEven1245Gives124(): void
    {
        // AC-04: HALF_EVEN — "1.245" rounds to "1.24" (banker's rounding, 4 is even)
        $result = $this->rounding->round('1.245', $this->eur, $this->decimals2);

        self::assertSame('1.24', $result);
    }

    public function testRoundHalfEven1255Gives126(): void
    {
        // AC-04: HALF_EVEN — "1.255" rounds to "1.26" (6 is even)
        $result = $this->rounding->round('1.255', $this->eur, $this->decimals2);

        self::assertSame('1.26', $result);
    }

    public function testRoundDoesNotThrowForNonChf(): void
    {
        // AccountingRounding works for any currency (unlike SwissCashRounding)
        $usd    = CurrencyCode::fromString('USD');
        $result = $this->rounding->round('1.245', $usd, $this->decimals2);

        self::assertSame('1.24', $result);
    }
}
