<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Scheduler;

use App\Shared\Infrastructure\Scheduler\DefaultScheduleProvider;
use App\Shared\Infrastructure\Scheduler\SchedulerHeartbeat;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Scheduler\Generator\MessageContext;

final class DefaultScheduleProviderTest extends TestCase
{
    public function testScheduleIsLockedNotStatefulAndBeatsEveryHour(): void
    {
        $lockFactory = new LockFactory(new InMemoryStore());
        $schedule    = new DefaultScheduleProvider($lockFactory)->getSchedule();

        $lock = $schedule->getLock();
        self::assertNotNull($lock);
        self::assertTrue($lock->acquire());
        self::assertFalse($lockFactory->createLock('scheduler.default')->acquire(), 'The lock comes from the injected factory.');

        self::assertNull($schedule->getState());

        $messages = $schedule->getRecurringMessages();
        self::assertCount(1, $messages);

        $trigger = $messages[0]->getTrigger();
        $first   = $trigger->getNextRunDate(new \DateTimeImmutable('2026-10-17 10:00:00'));
        self::assertNotNull($first);
        $second = $trigger->getNextRunDate($first);
        self::assertNotNull($second);
        self::assertSame(3600, $second->getTimestamp() - $first->getTimestamp());

        $context  = new MessageContext('default', $messages[0]->getId(), $trigger, $first);
        $produced = [...$messages[0]->getMessages($context)];
        self::assertCount(1, $produced);
        self::assertInstanceOf(SchedulerHeartbeat::class, $produced[0]);
    }
}
