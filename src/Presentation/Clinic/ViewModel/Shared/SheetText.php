<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Shared;

/**
 * French wording helpers shared by the client and animal sheets.
 */
final class SheetText
{
    /**
     * Formats a date with an ICU pattern, in the date's own time zone.
     */
    public static function date(\DateTimeImmutable $date, string $pattern): string
    {
        $formatter = new \IntlDateFormatter(
            'fr_FR',
            \IntlDateFormatter::NONE,
            \IntlDateFormatter::NONE,
            $date->getTimezone(),
            \IntlDateFormatter::GREGORIAN,
            $pattern,
        );

        $formatted = $formatter->format($date);

        return false === $formatted ? $date->format('d/m/Y') : $formatted;
    }

    public static function sentence(string $text): string
    {
        return mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1);
    }

    public static function weight(int $grams): string
    {
        return rtrim(rtrim(number_format($grams / 1000, 3, ',', ' '), '0'), ',') . ' kg';
    }

    public static function money(int $minorUnits, string $currency): string
    {
        return (new \NumberFormatter('fr_FR', \NumberFormatter::CURRENCY))->formatCurrency($minorUnits / 100, $currency);
    }

    public static function plural(int $count, string $singular, string $plural): string
    {
        return $count . ' ' . ($count > 1 ? $plural : $singular);
    }
}
