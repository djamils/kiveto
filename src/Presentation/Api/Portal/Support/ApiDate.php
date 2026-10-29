<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Support;

/** Dates of the Portal API: ISO 8601 with offset, in UTC for now. */
final class ApiDate
{
    public static function format(\DateTimeImmutable $at): string
    {
        return $at->setTimezone(new \DateTimeZone('UTC'))->format(\DATE_ATOM);
    }

    public static function formatNullable(?\DateTimeImmutable $at): ?string
    {
        return null === $at ? null : self::format($at);
    }
}
