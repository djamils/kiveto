<?php

declare(strict_types=1);

namespace App\System\Notification\Domain\Message;

/**
 * The alphabet an SMS is sent in, which sets how many characters fit in a
 * billed segment.
 */
enum SmsEncoding: string
{
    case GSM_7 = 'GSM-7';
    case UCS_2 = 'UCS-2';

    /** Characters (GSM-7 septets or UTF-16 units) of a single-segment SMS. */
    public function singleSegmentLength(): int
    {
        return match ($this) {
            self::GSM_7 => 160,
            self::UCS_2 => 70,
        };
    }

    /** Characters per segment once the SMS is split (the rest is the concatenation header). */
    public function multipartSegmentLength(): int
    {
        return match ($this) {
            self::GSM_7 => 153,
            self::UCS_2 => 67,
        };
    }
}
