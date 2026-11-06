<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Infrastructure\Scheduler;

use App\Context\Clinic\Application\Command\Account\CompleteClinicOnboardings\CompleteClinicOnboardings;
use App\Context\Clinic\Application\Command\Staff\RecordVeterinarianDailyCounts\RecordVeterinarianDailyCounts;
use App\Context\Clinic\Infrastructure\Scheduler\ClinicScheduleProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Scheduler\Generator\MessageContext;

final class ClinicScheduleProviderTest extends TestCase
{
    public function testVeterinariansAreCountedEveryNightAt0007UnderALock(): void
    {
        $lockFactory = new LockFactory(new InMemoryStore());
        $schedule    = new ClinicScheduleProvider($lockFactory)->getSchedule();

        $lock = $schedule->getLock();
        self::assertNotNull($lock);
        self::assertTrue($lock->acquire());
        self::assertFalse($lockFactory->createLock('scheduler.clinic')->acquire());
        self::assertNull($schedule->getState());

        $messages = $schedule->getRecurringMessages();
        self::assertCount(2, $messages);

        $trigger = $messages[0]->getTrigger();
        $first   = $trigger->getNextRunDate(new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')));
        self::assertNotNull($first);
        self::assertSame('00:07', $first->setTimezone(new \DateTimeZone('Europe/Paris'))->format('H:i'));

        $produced = [...$messages[0]->getMessages(new MessageContext('clinic', $messages[0]->getId(), $trigger, $first))];
        self::assertCount(1, $produced);
        self::assertInstanceOf(RecordVeterinarianDailyCounts::class, $produced[0]);
        self::assertNull($produced[0]->clinicId);
    }

    public function testOnboardingsAreCompletedEveryFifteenMinutes(): void
    {
        $messages = new ClinicScheduleProvider(new LockFactory(new InMemoryStore()))->getSchedule()->getRecurringMessages();
        $trigger  = $messages[1]->getTrigger();
        $from     = new \DateTimeImmutable('2026-11-06 10:00:00', new \DateTimeZone('UTC'));
        $next     = $trigger->getNextRunDate($from);
        self::assertNotNull($next);
        $after = $trigger->getNextRunDate($next);
        self::assertNotNull($after);

        self::assertSame(900, $after->getTimestamp() - $next->getTimestamp());

        $produced = [...$messages[1]->getMessages(new MessageContext('clinic', $messages[1]->getId(), $trigger, $next))];
        self::assertCount(1, $produced);
        self::assertInstanceOf(CompleteClinicOnboardings::class, $produced[0]);
    }
}
