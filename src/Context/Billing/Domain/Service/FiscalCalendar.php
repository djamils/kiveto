<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\Service;

/**
 * The calendar an invoice is dated and numbered in: local time in Europe/Paris.
 *
 * The clock runs in UTC, but the issue date printed on the PDF and transmitted
 * (BT-2) is the French local date, and the number's year must be the same: an
 * invoice issued on 1 January at 00:30 in Paris is dated and numbered in the
 * new year.
 */
final class FiscalCalendar
{
    private const string TIME_ZONE = 'Europe/Paris';

    public static function local(\DateTimeImmutable $at): \DateTimeImmutable
    {
        return $at->setTimezone(new \DateTimeZone(self::TIME_ZONE));
    }

    public static function year(\DateTimeImmutable $at): int
    {
        return (int) self::local($at)->format('Y');
    }
}
