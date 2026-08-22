<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Form\Shared;

/**
 * Reads a quantity typed into a form.
 *
 * The domain keeps quantities as exact decimal strings, so this normalises the
 * comma and the spaces and hands back a string — turning it into a float on the
 * way in is what would lose a hundredth of a millilitre.
 */
final class QuantityParser
{
    /** What the domain stores: six decimals. */
    private const int SCALE = 6;

    /**
     * @return numeric-string|null null when what was typed is not a quantity
     */
    public function __invoke(string $raw): ?string
    {
        return self::normalize($raw);
    }

    /**
     * @return numeric-string|null
     */
    public static function normalize(string $raw, bool $allowNegative = false): ?string
    {
        $value = str_replace([' ', "\u{202f}", "\u{a0}", ','], ['', '', '', '.'], mb_trim($raw));

        $pattern = $allowNegative ? '/^-?\d+(\.\d{1,6})?$/' : '/^\d+(\.\d{1,6})?$/';

        if (1 !== preg_match($pattern, $value)) {
            return null;
        }

        /** @var numeric-string $amount */
        $amount = $value;

        return bcadd($amount, '0', self::SCALE);
    }
}
