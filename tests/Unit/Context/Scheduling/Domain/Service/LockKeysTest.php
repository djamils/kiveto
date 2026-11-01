<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Domain\Service;

use App\Context\Scheduling\Domain\Service\LockKeys;
use App\Context\Scheduling\Domain\ValueObject\PractitionerDay;
use App\Context\Scheduling\Domain\ValueObject\RecurrenceRule;
use App\Context\Scheduling\Domain\ValueObject\TimeRange;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LockKeysTest extends TestCase
{
    private const string ROUSSEAU = '01960000-0000-7000-8000-0000000d0001';
    private const string LAMBERT  = '01960000-0000-7000-8000-0000000d0002';

    /**
     * @param list<string> $dates
     */
    #[DataProvider('provideASlotLocksEveryClinicLocalDateItTouchesCases')]
    public function testASlotLocksEveryClinicLocalDateItTouches(string $startUtc, string $endUtc, array $dates): void
    {
        $keys = LockKeys::forSlot(UserId::fromString(self::ROUSSEAU), self::utc($startUtc), self::utc($endUtc), new \DateTimeZone('Europe/Paris'));

        self::assertSame(array_map(static fn (string $date): string => self::ROUSSEAU . '|' . $date, $dates), self::keys($keys));
    }

    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function provideASlotLocksEveryClinicLocalDateItTouchesCases(): iterable
    {
        // AC 33: 00:30 local on 12 March is still 11 March in UTC
        yield '00:30 local' => ['2026-03-11 23:30', '2026-03-12 00:00', ['2026-03-12']];
        yield '23:30–00:30 local' => ['2026-03-11 22:30', '2026-03-11 23:30', ['2026-03-11', '2026-03-12']];
        yield 'ends at midnight (exclusive)' => ['2026-03-11 22:00', '2026-03-11 23:00', ['2026-03-11']];
        yield 'over two midnights' => ['2026-03-11 22:30', '2026-03-13 23:30', ['2026-03-11', '2026-03-12', '2026-03-13', '2026-03-14']];
        yield 'across the spring DST change' => ['2026-03-28 22:30', '2026-03-29 02:00', ['2026-03-28', '2026-03-29']];
        yield 'empty slot' => ['2026-03-11 09:00', '2026-03-11 09:00', ['2026-03-11']];
    }

    public function testAOneOffBlockLocksItsDateWhateverToday(): void
    {
        $keys = LockKeys::forBlock(UserId::fromString(self::ROUSSEAU), new TimeRange('2026-01-05', '08:00', '12:00'), RecurrenceRule::none(), '2026-04-01');

        self::assertSame([self::ROUSSEAU . '|2026-01-05'], self::keys($keys));
    }

    public function testARecurringBlockLocksItsOccurrencesFromTodayUpToUntil(): void
    {
        $keys = LockKeys::forBlock(UserId::fromString(self::ROUSSEAU), new TimeRange('2026-03-02', '08:00', '12:00'), RecurrenceRule::weekly('2026-04-20'), '2026-04-01');

        self::assertSame([self::ROUSSEAU . '|2026-04-06', self::ROUSSEAU . '|2026-04-13', self::ROUSSEAU . '|2026-04-20'], self::keys($keys));
    }

    public function testAnOpenEndedRecurringBlockLocksSixtyDaysAhead(): void
    {
        $keys = LockKeys::forBlock(UserId::fromString(self::ROUSSEAU), new TimeRange('2026-03-02', '08:00', '12:00'), RecurrenceRule::daily(), '2026-04-01');

        self::assertCount(61, $keys);
        self::assertSame(self::ROUSSEAU . '|2026-05-31', $keys[60]->key());
    }

    public function testARecurringBlockOverInThePastLocksNothing(): void
    {
        self::assertSame([], LockKeys::forBlock(UserId::fromString(self::ROUSSEAU), new TimeRange('2026-03-02', '08:00', '12:00'), RecurrenceRule::daily('2026-03-10'), '2026-04-01'));
    }

    public function testMergeDeduplicatesAndSortsByPractitionerThenDate(): void
    {
        $rousseau = UserId::fromString(self::ROUSSEAU);
        $lambert  = UserId::fromString(self::LAMBERT);

        $keys = LockKeys::merge(
            [new PractitionerDay($lambert, '2026-03-11'), new PractitionerDay($rousseau, '2026-03-12')],
            [new PractitionerDay($rousseau, '2026-03-11'), new PractitionerDay($lambert, '2026-03-11')],
        );

        self::assertSame([self::ROUSSEAU . '|2026-03-11', self::ROUSSEAU . '|2026-03-12', self::LAMBERT . '|2026-03-11'], self::keys($keys));
        self::assertSame([], LockKeys::merge());
    }

    public function testAPractitionerDayNeedsALocalDate(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PractitionerDay(UserId::fromString(self::ROUSSEAU), '11/03/2026');
    }

    /**
     * @param list<PractitionerDay> $keys
     *
     * @return list<string>
     */
    private static function keys(array $keys): array
    {
        return array_map(static fn (PractitionerDay $day): string => $day->key(), $keys);
    }

    private static function utc(string $at): \DateTimeImmutable
    {
        return new \DateTimeImmutable($at, new \DateTimeZone('UTC'));
    }
}
