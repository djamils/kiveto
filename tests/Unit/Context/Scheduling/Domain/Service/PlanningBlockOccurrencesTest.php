<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Domain\Service;

use App\Context\Scheduling\Domain\Booking\BusyInterval;
use App\Context\Scheduling\Domain\Service\PlanningBlockOccurrences;
use App\Context\Scheduling\Domain\ValueObject\RecurrenceRule;
use App\Context\Scheduling\Domain\ValueObject\TimeRange;
use PHPUnit\Framework\TestCase;

/**
 * A block of 08:00–12:00 Paris anchored on Tuesday 21 April 2026; today is
 * 1 April 2026 unless said otherwise.
 */
final class PlanningBlockOccurrencesTest extends TestCase
{
    public function testAOneOffBlockReadsItsOwnWindowWhateverToday(): void
    {
        $block = $this->block(RecurrenceRule::none(), today: '2026-05-01');

        self::assertSame(['2026-04-21 06:00', '2026-04-21 10:00'], [$this->utc($block->fromUtc()), $this->utc($block->toUtc())]);
        self::assertCount(1, $block->held([$this->at('2026-04-21 07:00')]));
        self::assertSame([], $block->held([$this->at('2026-04-28 07:00')]));
    }

    public function testARecurringBlockReadsFromItsFirstFutureOccurrenceToItsUntil(): void
    {
        $block = $this->block(RecurrenceRule::weekly('2026-06-30'));
        self::assertSame(['2026-04-21 06:00', '2026-06-30 10:00'], [$this->utc($block->fromUtc()), $this->utc($block->toUtc())]);

        $started = $this->block(RecurrenceRule::weekly(), today: '2026-05-06');
        self::assertSame(['2026-05-06 06:00', '9999-12-31 00:00'], [$this->utc($started->fromUtc()), $this->utc($started->toUtc())]);
    }

    public function testARecurringBlockHoldsTheAppointmentsOfEveryFutureOccurrence(): void
    {
        $block = $this->block(RecurrenceRule::weekly());

        $held = $block->held([
            $this->at('2026-04-21 07:00'),
            // 196 days later, in winter time (07:00–11:00 UTC)
            $this->at('2026-11-03 07:30'),
            // A Wednesday
            $this->at('2026-04-22 07:00'),
            // Ends as the occurrence starts
            $this->at('2026-04-28 05:30'),
        ]);

        self::assertCount(2, $held);
        self::assertSame('2026-11-03 07:30', $this->utc($held[1]->start));
    }

    public function testThePastOccurrencesOfARecurringBlockAreLeftOut(): void
    {
        $block = $this->block(RecurrenceRule::daily(), today: '2026-04-23');

        self::assertSame([], $block->held([$this->at('2026-04-22 07:00')]));
        self::assertCount(1, $block->held([$this->at('2026-04-23 07:00')]));
    }

    public function testAnAppointmentAcrossMidnightMeetsTheOccurrenceOfItsSecondDay(): void
    {
        $block = new PlanningBlockOccurrences(new TimeRange('2026-04-21', '00:00', '02:00'), RecurrenceRule::daily(), new \DateTimeZone('Europe/Paris'), '2026-04-01');

        // 23:30 on the 21st to 00:30 on the 22nd, Paris
        $overnight = $this->at('2026-04-21 21:30', 60);

        self::assertCount(1, $block->held([$overnight]));
        self::assertFalse($block->contains($overnight));
        self::assertTrue($block->contains($this->at('2026-04-21 22:00', 30)));
    }

    public function testContainsTellsAnAppointmentInsideAnOccurrenceFromOneStraddlingIt(): void
    {
        $block = $this->block(RecurrenceRule::weekdays());

        self::assertTrue($block->contains($this->at('2026-04-22 06:00', 240)));
        self::assertFalse($block->contains($this->at('2026-04-22 09:45')));
        self::assertFalse($block->contains($this->at('2026-04-25 07:00')));
    }

    public function testTheBusiestOccurrenceCountsPerOccurrence(): void
    {
        $block = $this->block(RecurrenceRule::weekly());

        self::assertSame(0, $block->busiestOccurrence([]));
        self::assertSame(2, $block->busiestOccurrence([
            $this->at('2026-04-21 07:00'),
            $this->at('2026-04-28 07:00'),
            $this->at('2026-04-28 08:00'),
            $this->at('2026-04-29 08:00'),
        ]));
    }

    private function block(RecurrenceRule $rule, string $today = '2026-04-01'): PlanningBlockOccurrences
    {
        return new PlanningBlockOccurrences(new TimeRange('2026-04-21', '08:00', '12:00'), $rule, new \DateTimeZone('Europe/Paris'), $today);
    }

    private function at(string $startsAtUtc, int $minutes = 30): BusyInterval
    {
        $start = new \DateTimeImmutable($startsAtUtc, new \DateTimeZone('UTC'));

        return BusyInterval::appointment($start, $start->modify(\sprintf('+%d minutes', $minutes)));
    }

    private function utc(\DateTimeImmutable $at): string
    {
        return $at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i');
    }
}
