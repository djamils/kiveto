<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Support;

/**
 * Dates of the Portal API: account and session instants in UTC, clinical
 * instants with the clinic's offset, date-only fields as `Y-m-d`.
 */
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

    /** An instant with the offset of the time zone it carries (the clinic's). */
    public static function formatLocal(\DateTimeImmutable $at): string
    {
        return $at->format(\DATE_ATOM);
    }

    /** A date-only field, without any time zone conversion. */
    public static function date(\DateTimeImmutable $on): string
    {
        return $on->format('Y-m-d');
    }

    public static function nullableDate(?\DateTimeImmutable $on): ?string
    {
        return null === $on ? null : self::date($on);
    }
}
