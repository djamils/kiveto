<?php

declare(strict_types=1);

namespace App\System\Notification\Infrastructure\Rendering;

use function Symfony\Component\String\u;

/**
 * The GSM 03.38 alphabet SMS are written in: its basic table (one septet
 * per character) and its extension table (an escape plus a septet, so two).
 *
 * Variable data printed in an SMS is transliterated to it, character by
 * character: what the alphabet has is kept (é è à ù ì ò Ç Ä Ö Ñ Ü…), the
 * rest is converted to ASCII (« Chloë » → « Chloe », « Côte » → « Cote »,
 * « ’ » → « ' »), and what has no ASCII form becomes « ? ».
 */
final class Gsm7
{
    private const string BASIC = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?"
        . '¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà';

    private const string EXTENSION = "^{}\\[~]|€\f";

    /** @var array<array-key, int>|null septets per character (digits become integer keys) */
    private static ?array $septets = null;

    /** Whether the whole text is written in the GSM-7 alphabet. */
    public static function isGsm7(string $text): bool
    {
        foreach (mb_str_split($text) as $char) {
            if (!isset(self::septets()[$char])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Septets the text takes, extension characters counting two; null when a
     * character is outside the alphabet.
     */
    public static function length(string $text): ?int
    {
        $length = 0;

        foreach (mb_str_split($text) as $char) {
            $septets = self::septets()[$char] ?? null;

            if (null === $septets) {
                return null;
            }

            $length += $septets;
        }

        return $length;
    }

    public static function transliterate(string $text): string
    {
        $out = '';

        foreach (mb_str_split($text) as $char) {
            if (isset(self::septets()[$char])) {
                $out .= $char;

                continue;
            }

            $ascii = u($char)->ascii()->toString();
            $out .= '' !== $ascii && self::isGsm7($ascii) ? $ascii : '?';
        }

        return $out;
    }

    /**
     * @return array<array-key, int>
     */
    private static function septets(): array
    {
        if (null === self::$septets) {
            self::$septets = array_fill_keys(mb_str_split(self::BASIC), 1) + array_fill_keys(mb_str_split(self::EXTENSION), 2);
        }

        return self::$septets;
    }
}
