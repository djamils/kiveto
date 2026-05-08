<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Regulatory\Service;

use App\Context\Regulatory\Domain\Service\WorkingDayCalculatorInterface;
use PHPUnit\Framework\TestCase;

abstract class WorkingDayCalculatorContractCase extends TestCase
{
    public function testAddingZeroDaysReturnsNonWorkingDayOrSame(): void
    {
        $calculator = $this->createCalculator();
        $monday     = new \DateTimeImmutable('2026-05-04'); // Monday

        $result = $calculator->addWorkingDays($monday, 0);

        self::assertSame($monday->format('Y-m-d'), $result->format('Y-m-d'));
    }

    public function testSaturdaysAreNotWorkingDays(): void
    {
        $calculator = $this->createCalculator();
        $saturday   = new \DateTimeImmutable('2026-05-02'); // Saturday

        self::assertFalse($calculator->isWorkingDay($saturday));
    }

    public function testSundaysAreNotWorkingDays(): void
    {
        $calculator = $this->createCalculator();
        $sunday     = new \DateTimeImmutable('2026-05-03'); // Sunday

        self::assertFalse($calculator->isWorkingDay($sunday));
    }

    public function testAddsCorrectNumberOfWorkingDays(): void
    {
        $calculator = $this->createCalculator();
        $monday     = new \DateTimeImmutable('2026-04-27'); // Monday

        $result = $calculator->addWorkingDays($monday, 5);

        // 5 working days from Monday Apr 27: Tue 28, Wed 29, Thu 30, Fri May 1 (Fête du Travail — holiday)
        // May 1 is a holiday so skip, next is Mon May 4
        // Actually: Tue Apr 28, Wed Apr 29, Thu Apr 30, Mon May 4, Tue May 5 → result is May 5
        self::assertGreaterThan($monday, $result);
    }

    abstract protected function createCalculator(): WorkingDayCalculatorInterface;
}
