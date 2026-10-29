<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Domain\Notification;

use App\Context\Scheduling\Domain\Notification\ReminderDelay;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReminderDelayTest extends TestCase
{
    private const string PARIS   = 'Europe/Paris';
    private const string CAYENNE = 'America/Cayenne';

    /**
     * Thursday 22/10/2026 09:30 in Paris (07:30 UTC): Wednesday 09:30,
     * Tuesday 09:30, Thursday 08:00 (AC 19).
     *
     * @return iterable<string, array{ReminderDelay, string}>
     */
    public static function provideEachDelayGivesItsDueTimeCases(): iterable
    {
        yield '24 h avant' => [ReminderDelay::H24, '2026-10-21 07:30'];
        yield '48 h avant' => [ReminderDelay::H48, '2026-10-20 07:30'];
        yield 'le matin même' => [ReminderDelay::SAME_DAY_8, '2026-10-22 06:00'];
    }

    #[DataProvider('provideEachDelayGivesItsDueTimeCases')]
    public function testEachDelayGivesItsDueTime(ReminderDelay $delay, string $dueUtc): void
    {
        $due = $delay->dueAt(self::utc('2026-10-22 07:30'), new \DateTimeZone(self::PARIS), self::utc('2026-10-13 08:42'));

        self::assertSame($dueUtc, $due?->format('Y-m-d H:i'));
        self::assertSame('UTC', $due->getTimezone()->getName());
    }

    public function testTheDueTimeKeepsTheLocalWallClockAcrossTheDstChange(): void
    {
        // Monday 26/10/2026 09:30 Paris (CET, 08:30 UTC), after the change of Sunday 25/10 (AC 22).
        $start = self::utc('2026-10-26 08:30');
        $paris = new \DateTimeZone(self::PARIS);
        $now   = self::utc('2026-10-13 08:42');

        self::assertSame('2026-10-25 08:30', ReminderDelay::H24->dueAt($start, $paris, $now)?->format('Y-m-d H:i'));
        self::assertSame('2026-10-24 07:30', ReminderDelay::H48->dueAt($start, $paris, $now)?->format('Y-m-d H:i'));
        self::assertSame('2026-10-26 07:00', ReminderDelay::SAME_DAY_8->dueAt($start, $paris, $now)?->format('Y-m-d H:i'));
    }

    public function testTheDueTimeFollowsTheClinicTimeZone(): void
    {
        // Monday 26/10/2026 09:30 in Cayenne (UTC−3, no DST).
        $start   = self::utc('2026-10-26 12:30');
        $cayenne = new \DateTimeZone(self::CAYENNE);
        $now     = self::utc('2026-10-13 08:42');

        self::assertSame('2026-10-25 12:30', ReminderDelay::H24->dueAt($start, $cayenne, $now)?->format('Y-m-d H:i'));
        self::assertSame('2026-10-24 12:30', ReminderDelay::H48->dueAt($start, $cayenne, $now)?->format('Y-m-d H:i'));
        self::assertSame('2026-10-26 11:00', ReminderDelay::SAME_DAY_8->dueAt($start, $cayenne, $now)?->format('Y-m-d H:i'));
    }

    public function testAnAppointmentBookedTooLateForTheDelayGetsNoReminder(): void
    {
        // Booked Wednesday 17:00 for Thursday 09:00 (Paris) with « 24 h avant » (AC 20).
        $start = self::utc('2026-10-22 07:00');
        $now   = self::utc('2026-10-21 15:00');
        $paris = new \DateTimeZone(self::PARIS);

        self::assertNull(ReminderDelay::H24->dueAt($start, $paris, $now));
        self::assertSame('2026-10-21 07:00', ReminderDelay::H24->plannedFor($start, $paris)?->format('Y-m-d H:i'));
        self::assertSame('2026-10-22 06:00', ReminderDelay::SAME_DAY_8->dueAt($start, $paris, $now)?->format('Y-m-d H:i'));
    }

    public function testNoMorningReminderWithinHalfAnHourOfTheStart(): void
    {
        $paris = new \DateTimeZone(self::PARIS);
        $now   = self::utc('2026-10-13 08:42');

        // 08:15 and 07:30 Paris (AC 20); 08:30 is the first start still reminded at 08:00.
        self::assertNull(ReminderDelay::SAME_DAY_8->dueAt(self::utc('2026-10-22 06:15'), $paris, $now));
        self::assertNull(ReminderDelay::SAME_DAY_8->dueAt(self::utc('2026-10-22 05:30'), $paris, $now));
        self::assertSame('2026-10-22 06:00', ReminderDelay::SAME_DAY_8->dueAt(self::utc('2026-10-22 06:30'), $paris, $now)?->format('Y-m-d H:i'));
    }

    public function testTheDefaultDelayIsTwentyFourHours(): void
    {
        self::assertSame(ReminderDelay::H24, ReminderDelay::default());
    }

    private static function utc(string $at): \DateTimeImmutable
    {
        return new \DateTimeImmutable($at, new \DateTimeZone('UTC'));
    }
}
