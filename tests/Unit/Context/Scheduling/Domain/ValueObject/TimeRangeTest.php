<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Domain\ValueObject;

use App\Context\Scheduling\Domain\Exception\InvalidPlanningBlockTimeRange;
use App\Context\Scheduling\Domain\ValueObject\TimeRange;
use PHPUnit\Framework\TestCase;

final class TimeRangeTest extends TestCase
{
    public function testValidConstruction(): void
    {
        $range = new TimeRange('2026-04-21', '08:00', '12:00');

        self::assertSame('2026-04-21', $range->date());
        self::assertSame('08:00', $range->startTime());
        self::assertSame('12:00', $range->endTime());
        self::assertSame(240, $range->durationMinutes());
    }

    public function testStartGreaterThanOrEqualToEndThrows(): void
    {
        $this->expectException(InvalidPlanningBlockTimeRange::class);
        new TimeRange('2026-04-21', '12:00', '08:00');
    }

    public function testStartEqualsEndThrows(): void
    {
        $this->expectException(InvalidPlanningBlockTimeRange::class);
        new TimeRange('2026-04-21', '08:00', '08:00');
    }

    public function testDurationBelowFifteenMinutesThrows(): void
    {
        $this->expectException(InvalidPlanningBlockTimeRange::class);
        new TimeRange('2026-04-21', '08:00', '08:14');
    }

    public function testInvalidHourThrows(): void
    {
        $this->expectException(InvalidPlanningBlockTimeRange::class);
        new TimeRange('2026-04-21', '25:00', '26:00');
    }

    public function testInvalidMinutesThrows(): void
    {
        $this->expectException(InvalidPlanningBlockTimeRange::class);
        new TimeRange('2026-04-21', '08:60', '09:00');
    }

    public function testInvalidDateFormatThrows(): void
    {
        $this->expectException(InvalidPlanningBlockTimeRange::class);
        new TimeRange('21-04-2026', '08:00', '12:00');
    }

    public function testToUtcConversion(): void
    {
        $range = new TimeRange('2026-04-21', '08:00', '12:00');
        $tz    = new \DateTimeZone('Europe/Paris');
        $start = $range->toUtcStart($tz);
        $end   = $range->toUtcEnd($tz);

        // Europe/Paris in April is UTC+2
        self::assertSame('2026-04-21 06:00:00', $start->format('Y-m-d H:i:s'));
        self::assertSame('2026-04-21 10:00:00', $end->format('Y-m-d H:i:s'));
    }

    public function testEquals(): void
    {
        $a = new TimeRange('2026-04-21', '08:00', '12:00');
        $b = new TimeRange('2026-04-21', '08:00', '12:00');
        $c = new TimeRange('2026-04-21', '09:00', '12:00');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }

    public function testExactlyFifteenMinutesIsValid(): void
    {
        $range = new TimeRange('2026-04-21', '08:00', '08:15');
        self::assertSame(15, $range->durationMinutes());
    }
}
