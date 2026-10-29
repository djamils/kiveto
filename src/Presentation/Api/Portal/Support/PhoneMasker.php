<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Support;

/** « +33612345678 » → « +33 6 •• •• •• 78 »: enough to recognise one's number. */
final class PhoneMasker
{
    public static function mask(string $phone): string
    {
        if (1 === preg_match('/^\+33(\d)\d{6}(\d{2})$/', $phone, $parts)) {
            return \sprintf('+33 %s •• •• •• %s', $parts[1], $parts[2]);
        }

        $visible = 4;

        return mb_substr($phone, 0, $visible) . str_repeat('•', max(0, mb_strlen($phone) - $visible - 2)) . mb_substr($phone, -2);
    }
}
