<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Domain\Service;

use App\Context\Scheduling\Domain\Service\PlanningBlockOverlap;
use App\Context\Scheduling\Domain\ValueObject\RecurrenceRule;
use App\Context\Scheduling\Domain\ValueObject\TimeRange;
use PHPUnit\Framework\TestCase;

/**
 * FU-06: blocks compared occurrence by occurrence. Today is Wednesday
 * 1 April 2026; the candidate is 08:00–12:00.
 */
final class PlanningBlockOverlapTest extends TestCase
{
    private const string TODAY = '2026-04-01';

    public function testAOneOffBlockIsComparedOnItsDate(): void
    {
        $oneOff = $this->candidate('2026-04-21', RecurrenceRule::none());

        self::assertSame(['2026-04-21', '2026-04-21'], [$oneOff->firstDate(), $oneOff->lastDate()]);
        // A weekly block from an earlier Tuesday occurs on it
        self::assertTrue($oneOff->meets('2026-04-07', '11:00:00', '13:00:00', RecurrenceRule::weekly()));
        // Ended the week before, or on Wednesdays
        self::assertFalse($oneOff->meets('2026-04-07', '11:00', '13:00', RecurrenceRule::weekly('2026-04-20')));
        self::assertFalse($oneOff->meets('2026-04-08', '11:00', '13:00', RecurrenceRule::weekly()));
    }

    public function testBlocksThatOnlyTouchDoNotOverlap(): void
    {
        $oneOff = $this->candidate('2026-04-21', RecurrenceRule::none());

        self::assertFalse($oneOff->meets('2026-04-21', '12:00:00', '13:00:00', RecurrenceRule::none()));
        self::assertFalse($oneOff->meets('2026-04-21', '07:00', '08:00', RecurrenceRule::none()));
        self::assertTrue($oneOff->meets('2026-04-21', '11:59', '13:00', RecurrenceRule::none()));
    }

    public function testARecurringBlockIsComparedOverTheHorizonFromToday(): void
    {
        // Began in March: compared from today to 60 days ahead
        $weekly = $this->candidate('2026-03-03', RecurrenceRule::weekly());
        self::assertSame(['2026-04-07', '2026-05-26'], [$weekly->firstDate(), $weekly->lastDate()]);

        // Weekly from another Tuesday, and a one-off on a later Tuesday
        self::assertTrue($weekly->meets('2026-05-19', '09:00', '10:00', RecurrenceRule::weekly()));
        self::assertTrue($weekly->meets('2026-04-28', '09:00', '10:00', RecurrenceRule::none()));
        // Beyond the horizon, or past
        self::assertFalse($weekly->meets('2026-06-02', '09:00', '10:00', RecurrenceRule::none()));
        self::assertFalse($weekly->meets('2026-03-10', '09:00', '10:00', RecurrenceRule::none()));
    }

    public function testARecurringBlockStartingLaterIsComparedFromItsFirstDate(): void
    {
        $daily = $this->candidate('2026-09-01', RecurrenceRule::daily('2026-09-03'));

        self::assertSame(['2026-09-01', '2026-09-03'], [$daily->firstDate(), $daily->lastDate()]);
    }

    public function testARecurringBlockOverIsComparedOnNothing(): void
    {
        $over = $this->candidate('2026-03-03', RecurrenceRule::weekly('2026-03-31'));

        self::assertNull($over->firstDate());
        self::assertNull($over->lastDate());
        self::assertFalse($over->meets('2026-03-03', '08:00', '12:00', RecurrenceRule::weekly()));
    }

    private function candidate(string $date, RecurrenceRule $rule): PlanningBlockOverlap
    {
        return new PlanningBlockOverlap(new TimeRange($date, '08:00', '12:00'), $rule, self::TODAY);
    }
}
