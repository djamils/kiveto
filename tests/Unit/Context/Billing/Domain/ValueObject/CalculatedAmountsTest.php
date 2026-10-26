<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Domain\ValueObject;

use App\Context\Billing\Domain\ValueObject\CalculatedAmounts;
use App\Context\Billing\Domain\ValueObject\LineAmounts;
use App\Context\Billing\Domain\ValueObject\RateBucket;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use App\Shared\Money\Domain\ValueObject\UnitPrice;
use PHPUnit\Framework\TestCase;

final class CalculatedAmountsTest extends TestCase
{
    public function testBucketsAreSortedHighestRateFirstAndTotalsAddUp(): void
    {
        $amounts = CalculatedAmounts::of(
            [],
            [
                RateBucket::of('5.5', 1000, 100, 50, $this->eur()),
                RateBucket::of('20', 2000, 0, 400, $this->eur()),
                RateBucket::of('10', 500, 0, 50, $this->eur()),
            ],
            $this->eur(),
        );

        self::assertSame(['20', '10', '5.5'], array_map(static fn (RateBucket $b): string => $b->ratePercent(), $amounts->buckets()));
        self::assertSame(3500, $amounts->totalLineNet()->minorUnits());
        self::assertSame(100, $amounts->totalAllowance()->minorUnits());
        self::assertSame(3400, $amounts->taxBasis()->minorUnits());
        self::assertSame(500, $amounts->totalVat()->minorUnits());
        self::assertSame(3900, $amounts->totalGross()->minorUnits());
        self::assertSame('EUR', $amounts->currency()->toString());
    }

    public function testNoneHasNothing(): void
    {
        $amounts = CalculatedAmounts::none($this->eur());

        self::assertSame([], $amounts->lines());
        self::assertSame([], $amounts->buckets());
        self::assertTrue($amounts->totalGross()->isZero());
    }

    public function testRateBucketDerivesBaseAndGross(): void
    {
        $bucket = RateBucket::of('20', 1000, 100, 180, $this->eur());

        self::assertSame(1000, $bucket->lineTotal()->minorUnits());
        self::assertSame(100, $bucket->allowance()->minorUnits());
        self::assertSame(900, $bucket->base()->minorUnits());
        self::assertSame(180, $bucket->vat()->minorUnits());
        self::assertSame(1080, $bucket->gross()->minorUnits());
        self::assertTrue($bucket->equals(RateBucket::of('20', 1000, 100, 180, $this->eur())));
        self::assertFalse($bucket->equals(RateBucket::of('20', 1000, 100, 181, $this->eur())));
        self::assertFalse($bucket->equals(RateBucket::of('10', 1000, 100, 180, $this->eur())));
    }

    public function testRateBucketRefusesAMalformedRate(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        RateBucket::of('20 %', 0, 0, 0, $this->eur());
    }

    public function testLineAmountsEquality(): void
    {
        $line = LineAmounts::of($this->money(833), $this->money(1000), UnitPrice::fromMicroUnits(8_333_333, $this->eur()));

        self::assertSame(833, $line->net()->minorUnits());
        self::assertSame(1000, $line->gross()->minorUnits());
        self::assertSame(8_333_333, $line->unitNet()->microUnits());
        self::assertTrue($line->equals(LineAmounts::of($this->money(833), $this->money(1000), UnitPrice::fromMicroUnits(8_333_333, $this->eur()))));
        self::assertFalse($line->equals(LineAmounts::of($this->money(834), $this->money(1000), UnitPrice::fromMicroUnits(8_333_333, $this->eur()))));
    }

    private function money(int $minorUnits): Money
    {
        return Money::fromMinorUnits($minorUnits, $this->eur());
    }

    private function eur(): CurrencyCode
    {
        return CurrencyCode::fromString('EUR');
    }
}
