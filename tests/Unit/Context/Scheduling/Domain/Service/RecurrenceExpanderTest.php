<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Domain\Service;

use App\Context\Scheduling\Domain\Service\RecurrenceExpander;
use App\Context\Scheduling\Domain\ValueObject\RecurrenceRule;
use PHPUnit\Framework\TestCase;

final class RecurrenceExpanderTest extends TestCase
{
    private RecurrenceExpander $expander;

    protected function setUp(): void
    {
        $this->expander = new RecurrenceExpander();
    }

    public function testNoneInRangeReturnsBaseDate(): void
    {
        $result = $this->expander->expand(
            RecurrenceRule::none(),
            '2026-04-21',
            new \DateTimeImmutable('2026-04-21'),
            new \DateTimeImmutable('2026-04-27'),
        );

        self::assertSame(['2026-04-21'], $result);
    }

    public function testNoneOutOfRangeReturnsEmpty(): void
    {
        $result = $this->expander->expand(
            RecurrenceRule::none(),
            '2026-03-01',
            new \DateTimeImmutable('2026-04-21'),
            new \DateTimeImmutable('2026-04-27'),
        );

        self::assertSame([], $result);
    }

    public function testDailyReturnsSeven(): void
    {
        $result = $this->expander->expand(
            RecurrenceRule::daily(),
            '2026-04-21',
            new \DateTimeImmutable('2026-04-21'),
            new \DateTimeImmutable('2026-04-27'),
        );

        self::assertCount(7, $result);
        self::assertSame('2026-04-21', $result[0]);
        self::assertSame('2026-04-27', $result[6]);
    }

    public function testWeeklyAnchoredMondayFourOccurrences(): void
    {
        $result = $this->expander->expand(
            RecurrenceRule::weekly(),
            '2026-04-21',
            new \DateTimeImmutable('2026-04-21'),
            new \DateTimeImmutable('2026-05-12'),
        );

        self::assertSame(['2026-04-21', '2026-04-28', '2026-05-05', '2026-05-12'], $result);
    }

    public function testWeeklyAnchoredWednesdayReturnsWednesdays(): void
    {
        $result = $this->expander->expand(
            RecurrenceRule::weekly(),
            '2026-04-22',
            new \DateTimeImmutable('2026-04-22'),
            new \DateTimeImmutable('2026-05-12'),
        );

        self::assertSame(['2026-04-22', '2026-04-29', '2026-05-06'], $result);
    }

    public function testWeekdaysExcludesWeekend(): void
    {
        // Apr 21 = Tue, Apr 22 = Wed, Apr 23 = Thu, Apr 24 = Fri (Apr 25 = Sat, Apr 26 = Sun — excluded)
        $result = $this->expander->expand(
            RecurrenceRule::weekdays(),
            '2026-04-21',
            new \DateTimeImmutable('2026-04-20'),
            new \DateTimeImmutable('2026-04-26'),
        );

        self::assertSame(['2026-04-21', '2026-04-22', '2026-04-23', '2026-04-24'], $result);
    }

    public function testUntilBoundsRecurrence(): void
    {
        $result = $this->expander->expand(
            RecurrenceRule::daily('2026-04-23'),
            '2026-04-21',
            new \DateTimeImmutable('2026-04-21'),
            new \DateTimeImmutable('2026-04-27'),
        );

        self::assertSame(['2026-04-21', '2026-04-22', '2026-04-23'], $result);
    }

    public function testRangeEndBeforeBaseReturnsEmpty(): void
    {
        $result = $this->expander->expand(
            RecurrenceRule::daily(),
            '2026-04-21',
            new \DateTimeImmutable('2026-04-14'),
            new \DateTimeImmutable('2026-04-20'),
        );

        self::assertSame([], $result);
    }

    public function testRangeStartAfterUntilReturnsEmpty(): void
    {
        $result = $this->expander->expand(
            RecurrenceRule::daily('2026-04-20'),
            '2026-04-14',
            new \DateTimeImmutable('2026-04-21'),
            new \DateTimeImmutable('2026-04-27'),
        );

        self::assertSame([], $result);
    }

    public function testRangeIsOneDayEqualToBaseDate(): void
    {
        $result = $this->expander->expand(
            RecurrenceRule::weekly(),
            '2026-04-21',
            new \DateTimeImmutable('2026-04-21'),
            new \DateTimeImmutable('2026-04-21'),
        );

        self::assertSame(['2026-04-21'], $result);
    }

    public function testUntilEqualsBaseDate(): void
    {
        $result = $this->expander->expand(
            RecurrenceRule::daily('2026-04-21'),
            '2026-04-21',
            new \DateTimeImmutable('2026-04-21'),
            new \DateTimeImmutable('2026-04-25'),
        );

        self::assertSame(['2026-04-21'], $result);
    }

    public function testInvertedRangeReturnsEmpty(): void
    {
        $result = $this->expander->expand(
            RecurrenceRule::daily(),
            '2026-04-21',
            new \DateTimeImmutable('2026-04-27'),
            new \DateTimeImmutable('2026-04-21'),
        );

        self::assertSame([], $result);
    }
}
