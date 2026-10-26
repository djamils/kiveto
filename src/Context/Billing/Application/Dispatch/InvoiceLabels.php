<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Dispatch;

/**
 * Amounts, rates and dates of invoice messages, in French. Dates are read in
 * Europe/Paris, like the invoice's own dates.
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
    public static function date(\DateTimeImmutable $at): string
    {
        return self::local($at)->format('d/m/Y');
    }

    /** « 16/10 » (SMS). */
    public static function shortDate(\DateTimeImmutable $at): string
    {
        return self::local($at)->format('d/m');
    }

    /** « 16 octobre 2026 ». */
    public static function longDate(\DateTimeImmutable $at): string
    {
        $local = self::local($at);

        return $local->format('j') . ' ' . self::MONTHS[(int) $local->format('n')] . ' ' . $local->format('Y');
    }

    private static function local(\DateTimeImmutable $at): \DateTimeImmutable
    {
        return $at->setTimezone(new \DateTimeZone(self::ZONE));
    }
}
