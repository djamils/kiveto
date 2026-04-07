<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scheduling\Domain\ValueObject;

use App\Scheduling\Domain\ValueObject\TimeSlot;
use PHPUnit\Framework\TestCase;

final class TimeSlotTest extends TestCase
{
    public function testCreateValidTimeSlot(): void
    {
        $startsAt = new \DateTimeImmutable('2026-02-01 09:00:00');
        $timeSlot = new TimeSlot($startsAt, 30);

        self::assertSame($startsAt, $timeSlot->startsAtUtc());
        self::assertSame(30, $timeSlot->durationMinutes());
    }

    public function testEndsAtUtcCalculation(): void
    {
        $startsAt = new \DateTimeImmutable('2026-02-01 09:00:00');
        $timeSlot = new TimeSlot($startsAt, 45);

        $expectedEnd = new \DateTimeImmutable('2026-02-01 09:45:00');
        self::assertEquals($expectedEnd, $timeSlot->endsAtUtc());
    }

    public function testCannotCreateTimeSlotWithZeroDuration(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Duration must be greater than zero.');

        new TimeSlot(new \DateTimeImmutable('2026-02-01 09:00:00'), 0);
    }

    public function testCannotCreateTimeSlotWithNegativeDuration(): void
    {
        $this->expectException(\DomainException::class);

        new TimeSlot(new \DateTimeImmutable('2026-02-01 09:00:00'), -10);
    }

    public function testOverlapsDetectionWhenOverlapping(): void
    {
        $slot1 = new TimeSlot(new \DateTimeImmutable('2026-02-01 09:00:00'), 30);
        $slot2 = new TimeSlot(new \DateTimeImmutable('2026-02-01 09:15:00'), 30);

        self::assertTrue($slot1->overlapsWith($slot2));
        self::assertTrue($slot2->overlapsWith($slot1));
    }

    public function testOverlapsDetectionWhenNotOverlapping(): void
    {
        $slot1 = new TimeSlot(new \DateTimeImmutable('2026-02-01 09:00:00'), 30);
        $slot2 = new TimeSlot(new \DateTimeImmutable('2026-02-01 09:30:00'), 30);

        self::assertFalse($slot1->overlapsWith($slot2));
        self::assertFalse($slot2->overlapsWith($slot1));
    }

    public function testOverlapsDetectionWhenOneContainsAnother(): void
    {
        $slot1 = new TimeSlot(new \DateTimeImmutable('2026-02-01 09:00:00'), 60);
        $slot2 = new TimeSlot(new \DateTimeImmutable('2026-02-01 09:15:00'), 20);

        self::assertTrue($slot1->overlapsWith($slot2));
        self::assertTrue($slot2->overlapsWith($slot1));
    }

    public function testEqualsReturnsTrueForSameTimeSlot(): void
    {
        $startsAt = new \DateTimeImmutable('2026-02-01 09:00:00');
        $slot1    = new TimeSlot($startsAt, 30);
        $slot2    = new TimeSlot($startsAt, 30);

        self::assertTrue($slot1->equals($slot2));
    }

    public function testEqualsReturnsFalseForDifferentTimeSlots(): void
    {
        $slot1 = new TimeSlot(new \DateTimeImmutable('2026-02-01 09:00:00'), 30);
        $slot2 = new TimeSlot(new \DateTimeImmutable('2026-02-01 09:00:00'), 45);

        self::assertFalse($slot1->equals($slot2));
    }
}
