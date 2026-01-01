<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Time;

use App\Shared\Infrastructure\Time\SystemClock;
use App\Tests\Shared\Time\FrozenClock;
use PHPUnit\Framework\TestCase;

final class ClockTest extends TestCase
{
    public function testSystemClockReturnsCurrentTime(): void
    {
        $clock = new SystemClock();

        $before = new \DateTimeImmutable();
        $now    = $clock->now();
        $after  = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before->getTimestamp(), $now->getTimestamp());
        self::assertLessThanOrEqual($after->getTimestamp(), $now->getTimestamp());
    }

    public function testFrozenClockReturnsSameTime(): void
    {
        $fixedTime = new \DateTimeImmutable('2025-01-01 12:00:00');
        $clock     = new FrozenClock($fixedTime);

        $now1 = $clock->now();
        usleep(1000); // Wait 1ms
        $now2 = $clock->now();

        self::assertSame($fixedTime, $now1);
        self::assertSame($fixedTime, $now2);
        self::assertSame($now1, $now2);
    }

    public function testFrozenClockIsDeterministicForTests(): void
    {
        $fixedTime = new \DateTimeImmutable('2025-12-25 10:30:45');
        $clock     = new FrozenClock($fixedTime);

        self::assertSame('2025-12-25 10:30:45', $clock->now()->format('Y-m-d H:i:s'));
    }
}
