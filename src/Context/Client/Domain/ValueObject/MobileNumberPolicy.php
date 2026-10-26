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
        '+594694', // Guyane
        '+596696', // Martinique
        '+50855',  // Saint-Pierre-et-Miquelon
        '+6877',   // Nouvelle-Calédonie
        '+6878',
        '+6879',
    ];

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

    /** A phone labelled « mobile » is trusted as one, whatever its prefix. */
    public static function canReceiveSms(string $e164, ContactLabel $label): bool
    {
        return ContactLabel::MOBILE === $label || self::isMobile($e164);
    }
}
