<?php

declare(strict_types=1);

namespace App\System\Notification\Infrastructure\Rendering;

use App\System\Notification\Domain\Message\SmsEncoding;

/**
 * How an SMS text is encoded and how many segments it is billed: GSM-7 when
 * every character is in the GSM alphabet (160 septets, 153 per part once
 * split, extension characters such as € counting two), else UCS-2 (70 UTF-16
 * units, 67 per part).
 *
 * A character is never split across two parts: an extension character (two
 * septets) or a surrogate pair (two UTF-16 units, e.g. an emoji) that does
 * not fit the end of a part starts the next one, as the handsets and the
 * provider cut the message.
 */
final class SmsSegmentCounter
{
    /**
     * @return array{SmsEncoding, int} the encoding and the number of segments (at least 1)
     */
    public static function count(string $text): array
    {
        $encoding = Gsm7::isGsm7($text) ? SmsEncoding::GSM_7 : SmsEncoding::UCS_2;
        $units    = array_map(
            static fn (string $char): int => SmsEncoding::GSM_7 === $encoding
                ? Gsm7::charLength($char) ?? 1
                : intdiv(\strlen(mb_convert_encoding($char, 'UTF-16BE', 'UTF-8')), 2),
            mb_str_split($text),
        );

        if (array_sum($units) <= $encoding->singleSegmentLength()) {
            return [$encoding, 1];
        }

        $segments = 1;
        $used     = 0;

        foreach ($units as $length) {
            if ($used + $length > $encoding->multipartSegmentLength()) {
                ++$segments;
                $used = 0;
            }

            $used += $length;
        }

        return [$encoding, $segments];
    }
}
