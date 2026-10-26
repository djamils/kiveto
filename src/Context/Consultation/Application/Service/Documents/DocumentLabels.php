<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Service\Documents;

use App\Context\Consultation\Application\Port\ReportPractitioner;

/**
 * Dates, amounts and names of the documents e-mail, in French. Dates are
 * read in Europe/Paris, like the printed documents.
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
    public static function date(\DateTimeImmutable $at): string
    {
        return self::local($at)->format('d/m/Y');
    }

    /** « 16 octobre 2026 ». */
    public static function longDate(\DateTimeImmutable $at): string
    {
        $local = self::local($at);

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

    private static function local(\DateTimeImmutable $at): \DateTimeImmutable
    {
        return $at->setTimezone(new \DateTimeZone(self::ZONE));
    }
}
