<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Dispatch;

/**
 * Amounts, rates and dates of invoice messages, in French. Dates are read in
 * Europe/Paris, like the invoice's own (fiscal) dates, unless a time zone is
 * given — the clinic's, for the day of a visit.
 */
final class InvoiceLabels
{
    private const string ZONE = 'Europe/Paris';

    private const array MONTHS = [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

    /** « 88,70 € », « 1 204,00 € ». */
    public static function euros(int $minorUnits): string
    {
        return number_format($minorUnits / 100, 2, ',', ' ') . ' €';
    }

    /** « 20 % », « 5,5 % » from « 20.00 », « 5.50 ». */
    public static function rate(string $percent): string
    {
        $trimmed = str_contains($percent, '.') ? mb_rtrim(mb_rtrim($percent, '0'), '.') : $percent;

        return str_replace('.', ',', '' !== $trimmed ? $trimmed : '0') . ' %';
    }

    /** « 16/10/2026 ». */
    public static function date(\DateTimeImmutable $at, ?\DateTimeZone $zone = null): string
    {
        return self::local($at, $zone)->format('d/m/Y');
    }

    /** « 16/10 » (SMS). */
    public static function shortDate(\DateTimeImmutable $at, ?\DateTimeZone $zone = null): string
    {
        return self::local($at, $zone)->format('d/m');
    }

    /** « 16 octobre 2026 ». */
    public static function longDate(\DateTimeImmutable $at, ?\DateTimeZone $zone = null): string
    {
        $local = self::local($at, $zone);

        return $local->format('j') . ' ' . self::MONTHS[(int) $local->format('n')] . ' ' . $local->format('Y');
    }

    private static function local(\DateTimeImmutable $at, ?\DateTimeZone $zone): \DateTimeImmutable
    {
        return $at->setTimezone($zone ?? new \DateTimeZone(self::ZONE));
    }
}
