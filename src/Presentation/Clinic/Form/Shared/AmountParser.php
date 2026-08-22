<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Form\Shared;

/**
 * Reads a price or an amount typed into a form.
 *
 * The field is a plain text input so a receptionist can type "45,50" as
 * naturally as "45.50"; anything else is rejected rather than silently rounded
 * to something nobody agreed to.
 */
final class AmountParser
{
    public static function toMinorUnits(string $raw): ?int
    {
        $value = str_replace([' ', "\u{202f}", "\u{a0}", ','], ['', '', '', '.'], mb_trim($raw));

        if (1 !== preg_match('/^\d+(\.\d{1,2})?$/', $value)) {
            return null;
        }

        return (int) round((float) $value * 100);
    }
}
