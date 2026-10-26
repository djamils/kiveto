<?php

declare(strict_types=1);

namespace App\System\Notification\Infrastructure\Rendering;

use App\System\Notification\Domain\Message\SmsEncoding;

/**
 * How an SMS text is encoded and how many segments it is billed: GSM-7 when
 * every character is in the GSM alphabet (160 septets, 153 per part once
 * split, extension characters such as € counting two), else UCS-2 (70 UTF-16
 * units, 67 per part).
 */
final class SmsSegmentCounter
{
    /**
     * @return array{SmsEncoding, int} the encoding and the number of segments (at least 1)
     */
    public static function count(string $text): array
    {
        $septets  = Gsm7::length($text);
        $encoding = null !== $septets ? SmsEncoding::GSM_7 : SmsEncoding::UCS_2;
        $length   = $septets ?? intdiv(\strlen(mb_convert_encoding($text, 'UTF-16BE', 'UTF-8')), 2);

        $segments = $length <= $encoding->singleSegmentLength()
            ? 1
            : (int) ceil($length / $encoding->multipartSegmentLength());

        return [$encoding, $segments];
    }
}
