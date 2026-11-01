<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Domain\Booking;

use App\Context\Scheduling\Domain\Booking\AvailabilityCalculator;
use App\Context\Scheduling\Domain\Booking\BookableInterval;
use App\Context\Scheduling\Domain\Booking\BusyInterval;
use App\Context\Scheduling\Domain\Booking\DaySlots;
use App\Context\Scheduling\Domain\Booking\LocalTime;
use App\Context\Scheduling\Domain\Booking\OpeningWindow;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AvailabilityCalculatorTest extends TestCase
{
    private const string MONDAY     = '2026-04-13';
    private const string FAR_PAST   = '2000-01-01T00:00:00Z';
    private const string ALL_DAY    = '00:00';
    private const string END_OF_DAY = '23:59';

    /**
     * @param list<array{string, string, int}>    $blocks   local start, end, capacity per hour
     * @param list<array{string, string}>         $opening  local opens, closes
     * @param list<array{string, string, string}> $busy     start, end (ISO instants), `appointment` | `unavailable`
     * @param list<string>                        $expected `H:iP`
     */
    #[DataProvider('provideTheOfferedStartsCases')]
    public function testTheOfferedStarts(
        string $tz,
        string $date,
        array $blocks,
        array $opening,
        array $busy,
        int $duration,
        int $grid,
        string $earliest,
        array $expected,
    ): void {
        [$day] = new AvailabilityCalculator()->days(
            $date,
            $date,
            '2099-12-31',
            new \DateTimeZone($tz),
            $duration,
            $grid,
            new \DateTimeImmutable($earliest),
            array_map(static fn (array $block): BookableInterval => new BookableInterval($date, LocalTime::minuteOf($block[0]), LocalTime::minuteOf($block[1]), $block[2]), $blocks),
            array_map(static fn (array $window): OpeningWindow => new OpeningWindow($date, LocalTime::minuteOf($window[0]), LocalTime::minuteOf($window[1])), $opening),
            array_map(static fn (array $interval): BusyInterval => 'appointment' === $interval[2]
                ? BusyInterval::appointment(new \DateTimeImmutable($interval[0]), new \DateTimeImmutable($interval[1]))
                : BusyInterval::unavailable(new \DateTimeImmutable($interval[0]), new \DateTimeImmutable($interval[1])), $busy),
        );

        self::assertSame($date, $day->date);
        self::assertTrue($day->open);
        self::assertSame($expected, self::starts($day));

        foreach ($day->slots as $slot) {
            // Emitted in the clinic's time zone, with its offset
            self::assertSame($tz, $slot->getTimezone()->getName());
        }
    }

    /**
     * @return iterable<string, array{string, string, list<array{string, string, int}>, list<array{string, string}>, list<array{string, string, string}>, int, int, string, list<string>}>
     */
    public static function provideTheOfferedStartsCases(): iterable
    {
        $paris = 'Europe/Paris';

        // AC 40: block 09:00–12:00 (unlimited), opening 08:30–12:30, 30 min, grid 15
        yield 'block inside the opening hours' => [$paris, self::MONDAY, [['09:00', '12:00', 0]], [['08:30', '12:30']], [], 30, 15, self::FAR_PAST, self::quarterHours('09:00', '11:30', '+02:00')];
        yield 'minus an occupying appointment' => [$paris, self::MONDAY, [['09:00', '12:00', 0]], [['08:30', '12:30']], [['2026-04-13T10:00:00+02:00', '2026-04-13T10:30:00+02:00', 'appointment']], 30, 15, self::FAR_PAST, ['09:00+02:00', '09:15+02:00', '09:30+02:00', '10:30+02:00', '10:45+02:00', '11:00+02:00', '11:15+02:00', '11:30+02:00']];
        yield 'from now + the lead time' => [$paris, self::MONDAY, [['09:00', '12:00', 0]], [['08:30', '12:30']], [], 30, 15, '2026-04-13T10:50:00+02:00', ['11:00+02:00', '11:15+02:00', '11:30+02:00']];
        yield 'the start equal to the lead time is offered' => [$paris, self::MONDAY, [['09:00', '12:00', 0]], [['08:30', '12:30']], [], 30, 15, '2026-04-13T11:15:00+02:00', ['11:15+02:00', '11:30+02:00']];
        yield 'clipped by the opening hours' => [$paris, self::MONDAY, [['08:00', '12:00', 0]], [['09:00', '11:00']], [], 30, 30, self::FAR_PAST, ['09:00+02:00', '09:30+02:00', '10:00+02:00', '10:30+02:00']];

        // AC 150: a one-off leave inside an occurrence of the weekly block
        yield 'one-off leave inside a weekly block' => [$paris, self::MONDAY, [['09:00', '12:00', 0]], [['08:00', '18:00']], [['2026-04-13T10:00:00+02:00', '2026-04-13T11:00:00+02:00', 'unavailable']], 30, 30, self::FAR_PAST, ['09:00+02:00', '09:30+02:00', '11:00+02:00', '11:30+02:00']];

        // A block spanning noon, split by the clinic's lunch break
        yield 'block split by an opening gap' => [$paris, self::MONDAY, [['11:00', '15:00', 0]], [['08:00', '12:00'], ['14:00', '18:00']], [], 30, 30, self::FAR_PAST, ['11:00+02:00', '11:30+02:00', '14:00+02:00', '14:30+02:00']];

        // AC 156: 08:00–12:30 + 12:30–18:00 are one window, 12:15–12:45 is offered
        yield 'adjacent opening slots merged' => [$paris, self::MONDAY, [['12:00', '13:00', 0]], [['12:30', '18:00'], ['08:00', '12:30']], [], 30, 15, self::FAR_PAST, ['12:00+02:00', '12:15+02:00', '12:30+02:00']];
        yield 'overlapping opening windows merged' => [$paris, self::MONDAY, [['12:00', '13:00', 0]], [['08:00', '12:45'], ['10:00', '12:30'], ['12:30', '18:00']], [], 30, 30, self::FAR_PAST, ['12:00+02:00', '12:30+02:00']];

        yield 'motif longer than the block' => [$paris, self::MONDAY, [['09:00', '10:00', 0]], [['08:00', '18:00']], [], 90, 15, self::FAR_PAST, []];
        yield 'two blocks: the slot fits inside one' => [$paris, self::MONDAY, [['09:00', '09:30', 0], ['09:30', '10:00', 0]], [['08:00', '18:00']], [], 45, 15, self::FAR_PAST, []];

        // Capacity: 1 per hour on 09:00–10:00 holds one booking; 0 means unlimited
        yield 'capacity unlimited' => [$paris, self::MONDAY, [['09:00', '10:00', 0]], [['08:00', '18:00']], [['2026-04-13T09:00:00+02:00', '2026-04-13T09:30:00+02:00', 'appointment']], 30, 30, self::FAR_PAST, ['09:30+02:00']];
        yield 'capacity full' => [$paris, self::MONDAY, [['09:00', '10:00', 1]], [['08:00', '18:00']], [['2026-04-13T09:00:00+02:00', '2026-04-13T09:30:00+02:00', 'appointment']], 30, 30, self::FAR_PAST, []];
        yield 'capacity with room' => [$paris, self::MONDAY, [['09:00', '10:00', 2]], [['08:00', '18:00']], [['2026-04-13T09:00:00+02:00', '2026-04-13T09:30:00+02:00', 'appointment']], 30, 30, self::FAR_PAST, ['09:30+02:00']];
        yield 'a leave does not use capacity' => [$paris, self::MONDAY, [['09:00', '10:00', 1]], [['08:00', '18:00']], [['2026-04-13T09:00:00+02:00', '2026-04-13T09:30:00+02:00', 'unavailable']], 30, 30, self::FAR_PAST, ['09:30+02:00']];
        yield 'an appointment outside the block does not use its capacity' => [$paris, self::MONDAY, [['09:00', '10:00', 1]], [['08:00', '18:00']], [['2026-04-13T10:00:00+02:00', '2026-04-13T10:30:00+02:00', 'appointment']], 30, 30, self::FAR_PAST, ['09:00+02:00', '09:30+02:00']];

        // Grid counted from the block's start
        yield 'grid 10' => [$paris, self::MONDAY, [['09:00', '10:00', 0]], [['08:00', '18:00']], [], 20, 10, self::FAR_PAST, ['09:00+02:00', '09:10+02:00', '09:20+02:00', '09:30+02:00', '09:40+02:00']];
        yield 'grid 30 from an off-grid block start' => [$paris, self::MONDAY, [['09:10', '10:10', 0]], [['08:00', '18:00']], [], 20, 30, self::FAR_PAST, ['09:10+02:00', '09:40+02:00']];
        yield 'grid 5' => [$paris, self::MONDAY, [['09:00', '09:30', 0]], [['08:00', '18:00']], [], 15, 5, self::FAR_PAST, ['09:00+02:00', '09:05+02:00', '09:10+02:00', '09:15+02:00']];

        // AC 42, last Sunday of March: 02:00–03:00 does not exist in Paris
        yield 'Paris spring forward, block across 02:00' => [$paris, '2026-03-29', [['01:00', '04:00', 0]], [[self::ALL_DAY, self::END_OF_DAY]], [], 30, 30, self::FAR_PAST, ['01:00+01:00', '01:30+01:00', '03:00+02:00', '03:30+02:00']];
        yield 'Paris spring forward, a slot across the gap lasts its real duration' => [$paris, '2026-03-29', [['01:00', '04:00', 0]], [[self::ALL_DAY, self::END_OF_DAY]], [], 60, 60, self::FAR_PAST, ['01:00+01:00', '03:00+02:00']];
        // 01:00–04:00 is 180 local minutes (2 real hours): « 1 per hour » holds 3
        yield 'Paris spring forward, capacity in local minutes' => [$paris, '2026-03-29', [['01:00', '04:00', 1]], [[self::ALL_DAY, self::END_OF_DAY]], [['2026-03-29T00:00:00Z', '2026-03-29T00:30:00Z', 'appointment'], ['2026-03-29T01:00:00Z', '2026-03-29T01:30:00Z', 'appointment']], 30, 30, self::FAR_PAST, ['01:30+01:00', '03:30+02:00']];
        yield 'Paris spring forward, capacity reached' => [$paris, '2026-03-29', [['01:00', '04:00', 1]], [[self::ALL_DAY, self::END_OF_DAY]], [['2026-03-29T00:00:00Z', '2026-03-29T00:30:00Z', 'appointment'], ['2026-03-29T00:30:00Z', '2026-03-29T01:00:00Z', 'appointment'], ['2026-03-29T01:00:00Z', '2026-03-29T01:30:00Z', 'appointment']], 30, 30, self::FAR_PAST, []];

        // AC 42, last Sunday of October: 02:00–03:00 happens twice; PHP reads the
        // repeated wall-clock time as its second (standard time) instant, so no instant comes twice
        yield 'Paris fall back, block across 02:00' => [$paris, '2026-10-25', [['01:00', '04:00', 0]], [[self::ALL_DAY, self::END_OF_DAY]], [], 30, 30, self::FAR_PAST, ['01:00+02:00', '01:30+02:00', '02:00+01:00', '02:30+01:00', '03:00+01:00', '03:30+01:00']];
        // 180 local minutes (4 real hours): « 1 per hour » holds 3, not 4
        yield 'Paris fall back, capacity in local minutes' => [$paris, '2026-10-25', [['01:00', '04:00', 1]], [[self::ALL_DAY, self::END_OF_DAY]], [['2026-10-24T23:00:00Z', '2026-10-24T23:30:00Z', 'appointment'], ['2026-10-25T00:00:00Z', '2026-10-25T00:30:00Z', 'appointment'], ['2026-10-25T02:00:00Z', '2026-10-25T02:30:00Z', 'appointment']], 30, 30, self::FAR_PAST, []];

        // No DST in Martinique: the offset never moves, even on Paris's DST Sundays
        yield 'Martinique on the March Sunday' => ['America/Martinique', '2026-03-29', [['01:00', '03:00', 0]], [[self::ALL_DAY, self::END_OF_DAY]], [], 30, 30, self::FAR_PAST, ['01:00-04:00', '01:30-04:00', '02:00-04:00', '02:30-04:00']];
        yield 'Martinique on the October Sunday' => ['America/Martinique', '2026-10-25', [['02:00', '03:00', 0]], [[self::ALL_DAY, self::END_OF_DAY]], [], 30, 30, self::FAR_PAST, ['02:00-04:00', '02:30-04:00']];

        // Nouméa (UTC+11): the local morning is the previous UTC day
        yield 'Nouméa date boundary' => ['Pacific/Noumea', self::MONDAY, [['08:00', '09:00', 0]], [['07:30', '12:00']], [['2026-04-12T21:30:00Z', '2026-04-12T22:00:00Z', 'appointment']], 30, 30, '2026-04-12T20:00:00Z', ['08:00+11:00']];
    }

    public function testTheRangeHasOneDayPerDateAndClosedOrFarDaysAreNotOpen(): void
    {
        $tz   = new \DateTimeZone('Europe/Paris');
        $days = new AvailabilityCalculator()->days(
            '2026-04-13',
            '2026-04-16',
            '2026-04-15',
            $tz,
            30,
            30,
            new \DateTimeImmutable(self::FAR_PAST),
            [
                new BookableInterval('2026-04-13', 540, 600, 0),
                new BookableInterval('2026-04-14', 540, 600, 0),
                new BookableInterval('2026-04-16', 540, 600, 0),
            ],
            [
                new OpeningWindow('2026-04-13', 480, 1080),
                // 14th: no opening window (closed day or exceptional closure)
                new OpeningWindow('2026-04-15', 480, 1080),
                new OpeningWindow('2026-04-16', 480, 1080),
            ],
            [],
        );

        self::assertSame(
            [
                ['2026-04-13', true, ['09:00+02:00', '09:30+02:00']],
                ['2026-04-14', false, []],
                // Open, but no block that day: no slot (HARD rule)
                ['2026-04-15', true, []],
                // Beyond the horizon
                ['2026-04-16', false, []],
            ],
            array_map(static fn (DaySlots $day): array => [$day->date, $day->open, self::starts($day)], $days),
        );
    }

    public function testOverlappingBlocksOfferEachStartOnce(): void
    {
        [$day] = new AvailabilityCalculator()->days(
            self::MONDAY,
            self::MONDAY,
            '2099-12-31',
            new \DateTimeZone('Europe/Paris'),
            30,
            30,
            new \DateTimeImmutable(self::FAR_PAST),
            [new BookableInterval(self::MONDAY, 600, 660, 0), new BookableInterval(self::MONDAY, 540, 660, 0)],
            [new OpeningWindow(self::MONDAY, 480, 1080)],
            [],
        );

        self::assertSame(['09:00+02:00', '09:30+02:00', '10:00+02:00', '10:30+02:00'], self::starts($day));
    }

    public function testASingleStartIsCheckedWithTheSameRules(): void
    {
        $calculator = new AvailabilityCalculator();
        $tz         = new \DateTimeZone('Pacific/Noumea');
        $bookable   = [new BookableInterval(self::MONDAY, 480, 600, 0)];
        $opening    = [new OpeningWindow(self::MONDAY, 420, 720)];
        $busy       = [BusyInterval::appointment(new \DateTimeImmutable('2026-04-12T22:00:00Z'), new \DateTimeImmutable('2026-04-12T22:30:00Z'))];
        $check      = static fn (string $start, string $lastDate = '2099-12-31'): bool => $calculator->isBookable(
            new \DateTimeImmutable($start),
            $lastDate,
            $tz,
            30,
            30,
            new \DateTimeImmutable(self::FAR_PAST),
            $bookable,
            $opening,
            $busy,
        );

        // 08:00 Nouméa on the 13th is 21:00 UTC on the 12th
        self::assertTrue($check('2026-04-12T21:00:00Z'));
        self::assertTrue($check('2026-04-13T08:30:00+11:00'));
        self::assertFalse($check('2026-04-13T09:00:00+11:00'), 'taken');
        self::assertFalse($check('2026-04-13T08:15:00+11:00'), 'off the grid');
        self::assertFalse($check('2026-04-14T08:00:00+11:00'), 'no block that day');
        self::assertFalse($check('2026-04-12T21:00:00Z', '2026-04-12'), 'beyond the horizon');
    }

    public function testADurationOrAGridStepMustBePositive(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new AvailabilityCalculator()->days(self::MONDAY, self::MONDAY, '2099-12-31', new \DateTimeZone('UTC'), 30, 0, new \DateTimeImmutable(), [], [], []);
    }

    /**
     * @return list<string>
     */
    private static function starts(DaySlots $day): array
    {
        return array_map(static fn (\DateTimeImmutable $slot): string => $slot->format('H:iP'), $day->slots);
    }

    /**
     * @return list<string>
     */
    private static function quarterHours(string $from, string $to, string $offset): array
    {
        $starts = [];

        for ($minute = LocalTime::minuteOf($from); $minute <= LocalTime::minuteOf($to); $minute += 15) {
            $starts[] = \sprintf('%02d:%02d%s', intdiv($minute, 60), $minute % 60, $offset);
        }

        return $starts;
    }
}
