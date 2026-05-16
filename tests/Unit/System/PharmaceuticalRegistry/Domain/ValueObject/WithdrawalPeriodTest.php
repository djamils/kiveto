<?php

declare(strict_types=1);

namespace Tests\Unit\System\PharmaceuticalRegistry\Domain\ValueObject;

use App\System\PharmaceuticalRegistry\Domain\ValueObject\WithdrawalPeriod;
use PHPUnit\Framework\TestCase;

final class WithdrawalPeriodTest extends TestCase
{
    public function testDaysToString(): void
    {
        self::assertSame('28:DAY', WithdrawalPeriod::days(28)->toString());
    }

    public function testHoursToString(): void
    {
        self::assertSame('12:HOUR', WithdrawalPeriod::hours(12)->toString());
    }

    public function testEquality(): void
    {
        self::assertTrue(WithdrawalPeriod::days(28)->equals(WithdrawalPeriod::days(28)));
        self::assertFalse(WithdrawalPeriod::days(28)->equals(WithdrawalPeriod::hours(28)));
        self::assertFalse(WithdrawalPeriod::days(7)->equals(WithdrawalPeriod::days(28)));
    }

    public function testZeroValueThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        WithdrawalPeriod::days(0);
    }
}
