<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Domain\SecondFactor;

/**
 * RFC 4648 base32 without padding: the alphabet authenticator apps read.
 */
final class Base32
{
    private const string ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function encode(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(\ord($byte)), 8, '0', \STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) {
            $encoded .= self::ALPHABET[(int) bindec(str_pad($chunk, 5, '0'))];
        }

        return '' === $bytes ? '' : $encoded;
    }

    public static function decode(string $text): string
    {
        $text = strtoupper(str_replace([' ', '='], '', $text));

        if (1 !== preg_match('/^[A-Z2-7]*$/', $text)) {
            throw new \InvalidArgumentException('Not a base32 text.');
        }

        $bits = '';
        foreach (str_split($text) as $char) {
            $bits .= str_pad(decbin((int) strpos(self::ALPHABET, $char)), 5, '0', \STR_PAD_LEFT);
        }

        $bytes = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (8 === \strlen($chunk)) {
                $bytes .= \chr((int) bindec($chunk));
            }
        }

        return '' === $text ? '' : $bytes;
    }
}
