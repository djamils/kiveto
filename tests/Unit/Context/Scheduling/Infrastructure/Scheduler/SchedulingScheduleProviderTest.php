<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Infrastructure\Scheduler;

use App\Context\Scheduling\Application\Command\PurgePractitionerDayLocks\PurgePractitionerDayLocks;
use App\Context\Scheduling\Application\Command\RequestDueReminders\RequestDueReminders;
use App\Context\Scheduling\Application\Command\ScanExpiredPendingAppointments\ScanExpiredPendingAppointments;
use App\Context\Scheduling\Infrastructure\Scheduler\SchedulingScheduleProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Scheduler\Generator\MessageContext;

final class SchedulingScheduleProviderTest extends TestCase
{
    public function testDueRemindersAreScannedEveryQuarterHourUnderALock(): void
    {
        $lockFactory = new LockFactory(new InMemoryStore());
        $schedule    = new SchedulingScheduleProvider($lockFactory)->getSchedule();

        $lock = $schedule->getLock();
        self::assertNotNull($lock);
        self::assertTrue($lock->acquire());
        self::assertFalse($lockFactory->createLock('scheduler.scheduling')->acquire());

        $messages = $schedule->getRecurringMessages();
        self::assertCount(3, $messages);

        $trigger = $messages[0]->getTrigger();
        $first   = $trigger->getNextRunDate(new \DateTimeImmutable('now'));
        self::assertNotNull($first);
        $second = $trigger->getNextRunDate($first);
        self::assertNotNull($second);
        // On the quarter hour (an appointment due at 09:30 is reminded by the 09:30 scan), every 900 s.
        self::assertSame([0, '00', 900], [(int) $first->format('i') % 15, $first->format('s'), $second->getTimestamp() - $first->getTimestamp()]);

        $produced = [...$messages[0]->getMessages(new MessageContext('scheduling', $messages[0]->getId(), $trigger, $first))];
        self::assertCount(1, $produced);
        self::assertInstanceOf(RequestDueReminders::class, $produced[0]);
    }

    public function testOldPractitionerDayLocksArePurgedOnTheFirstOfEveryMonth(): void
    {
        $messages = new SchedulingScheduleProvider(new LockFactory(new InMemoryStore()))->getSchedule()->getRecurringMessages();
        self::assertArrayHasKey(2, $messages);

        $trigger = $messages[2]->getTrigger();
        $first   = $trigger->getNextRunDate(new \DateTimeImmutable('now'));
        self::assertNotNull($first);
        $second = $trigger->getNextRunDate($first);
        self::assertNotNull($second);

        $paris = new \DateTimeZone('Europe/Paris');
        self::assertSame(['01', '04:17'], [$first->setTimezone($paris)->format('d'), $first->setTimezone($paris)->format('H:i')]);
        self::assertSame(['01', '04:17'], [$second->setTimezone($paris)->format('d'), $second->setTimezone($paris)->format('H:i')]);
        self::assertSame(1, (int) $first->diff($second)->format('%m') + 12 * (int) $first->diff($second)->format('%y'));

        $produced = [...$messages[2]->getMessages(new MessageContext('scheduling', $messages[2]->getId(), $trigger, $first))];
        self::assertCount(1, $produced);
        self::assertInstanceOf(PurgePractitionerDayLocks::class, $produced[0]);
    }

    public function testExpiredPendingRequestsAreScannedEveryQuarterHour(): void
    {
        $messages = new SchedulingScheduleProvider(new LockFactory(new InMemoryStore()))->getSchedule()->getRecurringMessages();
        self::assertArrayHasKey(1, $messages);

        $trigger = $messages[1]->getTrigger();
        $first   = $trigger->getNextRunDate(new \DateTimeImmutable('now'));
        self::assertNotNull($first);
        $second = $trigger->getNextRunDate($first);
        self::assertNotNull($second);
        self::assertSame([0, '00', 900], [(int) $first->format('i') % 15, $first->format('s'), $second->getTimestamp() - $first->getTimestamp()]);

        $produced = [...$messages[1]->getMessages(new MessageContext('scheduling', $messages[1]->getId(), $trigger, $first))];
        self::assertCount(1, $produced);
        self::assertInstanceOf(ScanExpiredPendingAppointments::class, $produced[0]);
    }
}
