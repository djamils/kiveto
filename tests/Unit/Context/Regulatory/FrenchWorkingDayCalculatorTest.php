<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Regulatory;

use App\Context\Regulatory\Domain\Service\FrenchWorkingDayCalculator;
use PHPUnit\Framework\TestCase;

final class FrenchWorkingDayCalculatorTest extends TestCase
{
    private FrenchWorkingDayCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new FrenchWorkingDayCalculator();
    }

    /**
     * Acceptance criterion: addWorkingDays(2026-04-30, 8) === 2026-05-15.
     *
     * Day-by-day trace from 2026-04-30:
     *   May 1  = Fête du Travail (skip)
     *   May 2  = Saturday (skip)
     *   May 3  = Sunday (skip)
     *   May 4  = Monday → day 1
     *   May 5  = Tuesday → day 2
     *   May 6  = Wednesday → day 3
     *   May 7  = Thursday → day 4
     *   May 8  = Victoire 1945 (skip)
     *   May 9  = Saturday (skip)
     *   May 10 = Sunday (skip)
     *   May 11 = Monday → day 5
     *   May 12 = Tuesday → day 6
     *   May 13 = Wednesday → day 7
     *   May 14 = Ascension 2026 (Easter 2026 = Apr 5, +39 = May 14) (skip)
     *   May 15 = Friday → day 8 ✓
     */
    public function testAddWorkingDays20260430Plus8Equals20260515(): void
    {
        $from   = new \DateTimeImmutable('2026-04-30');
        $result = $this->calculator->addWorkingDays($from, 8);

        self::assertSame('2026-05-15', $result->format('Y-m-d'));
    }

    public function testAddWorkingDaysSkipsWeekend(): void
    {
        // Friday + 1 working day = Monday (skip Sat, Sun)
        $from   = new \DateTimeImmutable('2026-01-09'); // Friday
        $result = $this->calculator->addWorkingDays($from, 1);

        self::assertSame('2026-01-12', $result->format('Y-m-d')); // Monday
    }

    public function testAddWorkingDaysSkipsJourFerieJan1(): void
    {
        // Dec 31 + 1 working day: Jan 1 is a jour férié, so result = Jan 2
        $from   = new \DateTimeImmutable('2025-12-31'); // Wednesday
        $result = $this->calculator->addWorkingDays($from, 1);

        self::assertSame('2026-01-02', $result->format('Y-m-d'));
    }

    public function testIsWorkingDayReturnsFalseForSaturday(): void
    {
        $saturday = new \DateTimeImmutable('2026-01-03'); // Saturday

        self::assertFalse($this->calculator->isWorkingDay($saturday));
    }

    public function testIsWorkingDayReturnsFalseForSunday(): void
    {
        $sunday = new \DateTimeImmutable('2026-01-04'); // Sunday

        self::assertFalse($this->calculator->isWorkingDay($sunday));
    }

    public function testIsWorkingDayReturnsFalseForJourFerie(): void
    {
        $labourDay = new \DateTimeImmutable('2026-05-01'); // Fête du Travail

        self::assertFalse($this->calculator->isWorkingDay($labourDay));
    }

    public function testIsWorkingDayReturnsTrueForNormalWeekday(): void
    {
        $monday = new \DateTimeImmutable('2026-01-05'); // Monday, not a jour férié

        self::assertTrue($this->calculator->isWorkingDay($monday));
    }

    /**
     * Easter 2026 = April 5.
     * Lundi de Pâques = April 6.
     * Ascension = April 5 + 39 = May 14.
     * Lundi de Pentecôte = April 5 + 50 = May 25.
     */
    public function testEasterBasedHolidays2026(): void
    {
        $lundiDePaques     = new \DateTimeImmutable('2026-04-06');
        $ascension         = new \DateTimeImmutable('2026-05-14');
        $lundiDePentecoste = new \DateTimeImmutable('2026-05-25');

        self::assertFalse($this->calculator->isWorkingDay($lundiDePaques));
        self::assertFalse($this->calculator->isWorkingDay($ascension));
        self::assertFalse($this->calculator->isWorkingDay($lundiDePentecoste));
    }

    public function testAllFixedJoursFeries2026(): void
    {
        $joursFeeries = [
            '2026-01-01', // Jour de l'An
            '2026-05-01', // Fête du Travail
            '2026-05-08', // Victoire 1945
            '2026-07-14', // Fête Nationale
            '2026-08-15', // Assomption
            '2026-11-01', // Toussaint
            '2026-11-11', // Armistice
            '2026-12-25', // Noël
        ];

        foreach ($joursFeeries as $date) {
            self::assertFalse(
                $this->calculator->isWorkingDay(new \DateTimeImmutable($date)),
                \sprintf('Expected %s to be a jour férié (non-working day)', $date),
            );
        }
    }
}
