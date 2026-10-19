<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Scheduler;

use App\Shared\Infrastructure\Scheduler\DefaultScheduleProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

/**
 * A scheduler worker that stops on a multiple of a task period always restarts
 * on that task's tick, and loses it every time. The compose time limit must
 * therefore never be a multiple of the shortest period of the schedule.
 */
final class SchedulerWorkerTimeLimitTest extends TestCase
{
    public function testWorkerTimeLimitIsNotAMultipleOfTheShortestTaskPeriod(): void
    {
        $compose = file_get_contents(\dirname(__DIR__, 5) . '/compose.yaml');
        self::assertIsString($compose);
        self::assertSame(1, preg_match('/messenger:consume scheduler_default\b.*--time-limit=(\d+)/', $compose, $match));
        $timeLimit = (int) $match[1];

        $periods = [];
        foreach (new DefaultScheduleProvider(new LockFactory(new InMemoryStore()))->getSchedule()->getRecurringMessages() as $message) {
            $first = $message->getTrigger()->getNextRunDate(new \DateTimeImmutable('2026-10-17 10:00:00'));
            self::assertNotNull($first);
            $second = $message->getTrigger()->getNextRunDate($first);
            self::assertNotNull($second);
            $periods[] = $second->getTimestamp() - $first->getTimestamp();
        }
        if ([] === $periods) {
            self::fail('The default schedule has no recurring message.');
        }

        $shortest = min($periods);
        self::assertGreaterThan(0, $shortest);
        self::assertNotSame(0, $timeLimit % $shortest, \sprintf('The scheduler worker stops every %d s, a multiple of the %d s task period.', $timeLimit, $shortest));
    }
}
