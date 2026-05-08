<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Regulatory\Jurisdiction\France;

use App\Context\Regulatory\Domain\Service\WorkingDayCalculatorInterface;
use App\Context\Regulatory\Jurisdiction\France\FrenchWorkingDayCalculator;
use App\Tests\Unit\Context\Regulatory\Service\WorkingDayCalculatorContractCase;

final class FrenchWorkingDayCalculatorTest extends WorkingDayCalculatorContractCase
{
    public function testMayFirstIsFrenchHoliday(): void
    {
        $calculator = $this->createCalculator();
        $mayFirst   = new \DateTimeImmutable('2026-05-01');

        self::assertFalse($calculator->isWorkingDay($mayFirst));
    }

    public function testJuly14IsFrenchHoliday(): void
    {
        $calculator  = $this->createCalculator();
        $bastilleDay = new \DateTimeImmutable('2026-07-14');

        self::assertFalse($calculator->isWorkingDay($bastilleDay));
    }

    public function testRegularMondayIsWorkingDay(): void
    {
        $calculator = $this->createCalculator();
        $monday     = new \DateTimeImmutable('2026-05-04'); // Monday after Labour Day

        self::assertTrue($calculator->isWorkingDay($monday));
    }

    public function testAddEightWorkingDaysFromApril30(): void
    {
        $calculator    = $this->createCalculator();
        $admissionDate = new \DateTimeImmutable('2026-04-30');

        // Apr 30 (Thu) +1=Fri May 1 (holiday), +2=Mon May 4, +3=Tue May 5,
        // +4=Wed May 6, +5=Thu May 7, +6=Fri May 8 (holiday), +7=Mon May 11,
        // +8=Tue May 12 (but Ascension May 14 2026? Let me count manually)
        // Actually Ascension 2026 is 39 days after Easter 2026 (Apr 5):
        //   Apr 5 + 39 = May 14, 2026
        // So: May 1 (holiday), May 8 (holiday), May 14 (Ascension = holiday)
        // 8 working days from Apr 30: May 4, May 5, May 6, May 7, May 11, May 12, May 13, May 15
        $result = $calculator->addWorkingDays($admissionDate, 8);

        self::assertSame('2026-05-15', $result->format('Y-m-d'));
    }

    protected function createCalculator(): WorkingDayCalculatorInterface
    {
        return new FrenchWorkingDayCalculator();
    }
}
