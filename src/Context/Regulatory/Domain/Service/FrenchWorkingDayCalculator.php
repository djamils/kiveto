<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Domain\Service;

/**
 * Pure domain service — no framework dependencies.
 *
 * Computes working days according to French labour law:
 * - Saturdays and Sundays are non-working.
 * - The following jours fériés are non-working:
 *   Fixed: 1 Jan, 1 May, 8 May, 14 Jul, 15 Aug, 1 Nov, 11 Nov, 25 Dec.
 *   Easter-based (Gregorian): Lundi de Pâques (+1), Ascension (+39), Lundi de Pentecôte (+50).
 * - Ponts (bridge days) are NOT included (alpha simplification).
 */
final class FrenchWorkingDayCalculator
{
    /**
     * Returns the date that is $days working days after $from.
     * Counting starts on the day AFTER $from.
     */
    public function addWorkingDays(\DateTimeImmutable $from, int $days): \DateTimeImmutable
    {
        $current   = $from;
        $remaining = $days;

        while ($remaining > 0) {
            $current = $current->modify('+1 day');

            if ($this->isWorkingDay($current)) {
                --$remaining;
            }
        }

        return $current;
    }

    /**
     * Returns true if the given date is a working day (not a weekend or jour férié).
     */
    public function isWorkingDay(\DateTimeImmutable $date): bool
    {
        $weekday = (int) $date->format('N'); // 1=Mon … 7=Sun

        if ($weekday >= 6) {
            return false;
        }

        $holidays = $this->getHolidaysForYear((int) $date->format('Y'));

        $ymd = $date->format('Y-m-d');

        foreach ($holidays as $holiday) {
            if ($holiday->format('Y-m-d') === $ymd) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns the list of jours fériés for a given year.
     *
     * @return list<\DateTimeImmutable>
     */
    private function getHolidaysForYear(int $year): array
    {
        $easter = $this->computeEasterSunday($year);

        return [
            // Fixed jours fériés
            new \DateTimeImmutable(\sprintf('%04d-01-01', $year)), // Jour de l'An
            new \DateTimeImmutable(\sprintf('%04d-05-01', $year)), // Fête du Travail
            new \DateTimeImmutable(\sprintf('%04d-05-08', $year)), // Victoire 1945
            new \DateTimeImmutable(\sprintf('%04d-07-14', $year)), // Fête Nationale
            new \DateTimeImmutable(\sprintf('%04d-08-15', $year)), // Assomption
            new \DateTimeImmutable(\sprintf('%04d-11-01', $year)), // Toussaint
            new \DateTimeImmutable(\sprintf('%04d-11-11', $year)), // Armistice
            new \DateTimeImmutable(\sprintf('%04d-12-25', $year)), // Noël

            // Easter-based jours fériés
            $easter->modify('+1 day'),  // Lundi de Pâques
            $easter->modify('+39 days'), // Ascension
            $easter->modify('+50 days'), // Lundi de Pentecôte
        ];
    }

    /**
     * Computes Easter Sunday using the Anonymous Gregorian algorithm (Meeus/Jones/Butcher).
     */
    private function computeEasterSunday(int $year): \DateTimeImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);

        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day   = (($h + $l - 7 * $m + 114) % 31) + 1;

        return new \DateTimeImmutable(\sprintf('%04d-%02d-%02d', $year, $month, $day));
    }
}
