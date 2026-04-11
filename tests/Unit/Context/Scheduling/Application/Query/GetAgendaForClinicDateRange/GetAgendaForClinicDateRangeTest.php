<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Application\Query\GetAgendaForClinicDateRange;

use App\Context\Scheduling\Application\Query\GetAgendaForClinicDateRange\GetAgendaForClinicDateRange;
use PHPUnit\Framework\TestCase;

final class GetAgendaForClinicDateRangeTest extends TestCase
{
    private const string CLINIC_ID = '12345678-9abc-def0-1234-56789abcdef0';

    public function testForDayComputesMidnightToEndOfDayInClinicTimezone(): void
    {
        $parisTz = new \DateTimeZone('Europe/Paris');

        $query = GetAgendaForClinicDateRange::forDay(
            clinicId: self::CLINIC_ID,
            date: new \DateTimeImmutable('2026-04-15 14:30:00', $parisTz),
            clinicTz: $parisTz,
        );

        // 2026-04-15 is during CEST (+02:00): local midnight = 22:00 UTC of the day before.
        self::assertSame('2026-04-14 22:00:00', $query->fromUtc->format('Y-m-d H:i:s'));
        self::assertSame('2026-04-15 21:59:59', $query->toUtc->format('Y-m-d H:i:s'));
        self::assertSame(self::CLINIC_ID, $query->clinicId);
        self::assertNull($query->practitionerUserId);
    }

    public function testForWeekComputesMondayToSundayInClinicTimezone(): void
    {
        $parisTz = new \DateTimeZone('Europe/Paris');

        $query = GetAgendaForClinicDateRange::forWeek(
            clinicId: self::CLINIC_ID,
            anchorDate: new \DateTimeImmutable('2026-04-15 14:30:00', $parisTz), // Wednesday
            clinicTz: $parisTz,
        );

        // Monday 2026-04-13 00:00 Paris = 2026-04-12 22:00 UTC
        // Sunday 2026-04-19 23:59:59 Paris = 2026-04-19 21:59:59 UTC
        self::assertSame('2026-04-12 22:00:00', $query->fromUtc->format('Y-m-d H:i:s'));
        self::assertSame('2026-04-19 21:59:59', $query->toUtc->format('Y-m-d H:i:s'));
    }

    public function testForWeekAtParisSundayLateNightDoesNotRollOverToNextWeek(): void
    {
        // UTC Sunday 2026-04-12 23:30 = Paris Monday 2026-04-13 01:30 (CEST).
        // In Paris that's already Monday → "monday this week" is 2026-04-13.
        $query = GetAgendaForClinicDateRange::forWeek(
            clinicId: self::CLINIC_ID,
            anchorDate: new \DateTimeImmutable('2026-04-12 23:30:00', new \DateTimeZone('UTC')),
            clinicTz: new \DateTimeZone('Europe/Paris'),
        );

        self::assertSame('2026-04-12 22:00:00', $query->fromUtc->format('Y-m-d H:i:s'));
        self::assertSame('2026-04-19 21:59:59', $query->toUtc->format('Y-m-d H:i:s'));
    }

    public function testConstructorAcceptsOptionalPractitionerUserId(): void
    {
        $practitionerUserId = '44444444-4444-4444-4444-444444444444';

        $query = GetAgendaForClinicDateRange::forDay(
            clinicId: self::CLINIC_ID,
            date: new \DateTimeImmutable('2026-04-15 14:30:00'),
            clinicTz: new \DateTimeZone('UTC'),
            practitionerUserId: $practitionerUserId,
        );

        self::assertSame($practitionerUserId, $query->practitionerUserId);
    }
}
