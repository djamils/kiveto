<?php

declare(strict_types=1);

namespace App\Context\Client\Domain\ValueObject;

/**
 * Tells a mobile number (one that can receive an SMS) from a landline, by
 * its E.164 prefix: metropolitan France and the overseas departments and
 * territories whose mobile ranges differ from their landlines.
 */
final class MobileNumberPolicy
{
    /** E.164 prefixes of mobile ranges, without spaces. */
    public const array MOBILE_PREFIXES = [
        '+336',    // France
        '+337',    // France
        '+26269',  // La Réunion
        '+262639', // Mayotte
        '+590690', // Guadeloupe, Saint-Martin, Saint-Barthélemy
        '+590691',
        '+594694', // Guyane
        '+596696', // Martinique
        '+596697',
        '+50855',  // Saint-Pierre-et-Miquelon
        '+6877',   // Nouvelle-Calédonie
        '+6878',
        '+6879',
        '+68987',  // Polynésie française
        '+68988',
        '+68989',
        '+681',    // Wallis-et-Futuna
    ];

    /**
     * E.164 prefixes of metropolitan landlines (01 to 05, 09): no SMS
     * reaches them, whatever label the number was given.
     */
    public const array LANDLINE_PREFIXES = ['+331', '+332', '+333', '+334', '+335', '+339'];

    public static function isMobile(string $e164): bool
    {
        $number = (string) preg_replace('/\s+/', '', $e164);

        foreach (self::MOBILE_PREFIXES as $prefix) {
            if (str_starts_with($number, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public static function isLandline(string $e164): bool
    {
        $number = (string) preg_replace('/\s+/', '', $e164);

        return array_any(self::LANDLINE_PREFIXES, static fn (string $prefix): bool => str_starts_with($number, $prefix));
    }

    /**
     * A mobile prefix can receive an SMS; a phone labelled « mobile » is
     * trusted as one (a foreign mobile, a range not listed above) unless
     * its prefix is a known landline.
     */
    public static function canReceiveSms(string $e164, ContactLabel $label): bool
    {
        return self::isMobile($e164) || (ContactLabel::MOBILE === $label && !self::isLandline($e164));
    }
}
