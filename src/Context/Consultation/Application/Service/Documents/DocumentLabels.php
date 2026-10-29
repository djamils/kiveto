<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Service\Documents;

use App\Context\Consultation\Application\Port\ReportPractitioner;

/**
 * Dates, amounts and names of the documents e-mail, in French. Dates are
 * read in the clinic's time zone when given, else in Europe/Paris.
 */
final class DocumentLabels
{
    private const string ZONE = 'Europe/Paris';

    private const array MONTHS = [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

    private const array TITLES = ['DR' => 'Dr ', 'PR' => 'Pr '];

    /** « 88,70 € », « 1 204,00 € ». */
    public static function euros(int $minorUnits): string
    {
        return number_format($minorUnits / 100, 2, ',', ' ') . ' €';
    }

    /** « 16/10/2026 ». */
    public static function date(\DateTimeImmutable $at, ?\DateTimeZone $zone = null): string
    {
        return self::local($at, $zone)->format('d/m/Y');
    }

    /** « 16 octobre 2026 ». */
    public static function longDate(\DateTimeImmutable $at, ?\DateTimeZone $zone = null): string
    {
        $local = self::local($at, $zone);

        return $local->format('j') . ' ' . self::MONTHS[(int) $local->format('n')] . ' ' . $local->format('Y');
    }

    /** « Dr Claire Lambert »; null when the practitioner has no name on file. */
    public static function practitioner(?ReportPractitioner $practitioner): ?string
    {
        $name = mb_trim((string) $practitioner?->displayName);

        if (null === $practitioner || '' === $name) {
            return null;
        }

        return (self::TITLES[$practitioner->professionalTitle ?? ''] ?? '') . $name;
    }

    private static function local(\DateTimeImmutable $at, ?\DateTimeZone $zone): \DateTimeImmutable
    {
        return $at->setTimezone($zone ?? new \DateTimeZone(self::ZONE));
    }
}
