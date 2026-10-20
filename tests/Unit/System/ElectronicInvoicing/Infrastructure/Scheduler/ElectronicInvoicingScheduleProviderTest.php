<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\ElectronicInvoicing\Infrastructure\Scheduler;

use App\System\ElectronicInvoicing\Application\Command\SyncAllPlatforms\SyncAllPlatforms;
use App\System\ElectronicInvoicing\Infrastructure\Scheduler\ElectronicInvoicingScheduleProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Scheduler\Generator\MessageContext;

final class ElectronicInvoicingScheduleProviderTest extends TestCase
{
    public function testScheduleIsLockedNotStatefulAndSynchronisesEveryFiveMinutes(): void
    {
        $lockFactory = new LockFactory(new InMemoryStore());
        $schedule    = new ElectronicInvoicingScheduleProvider($lockFactory)->getSchedule();

        $lock = $schedule->getLock();
        self::assertNotNull($lock);
        self::assertTrue($lock->acquire());
        self::assertFalse($lockFactory->createLock('scheduler.electronic_invoicing')->acquire());
        self::assertNull($schedule->getState());

        $messages = $schedule->getRecurringMessages();
        self::assertCount(1, $messages);

        $trigger = $messages[0]->getTrigger();
        $first   = $trigger->getNextRunDate(new \DateTimeImmutable());
        self::assertNotNull($first);
        $second = $trigger->getNextRunDate($first);
        self::assertNotNull($second);
        self::assertSame(300, $second->getTimestamp() - $first->getTimestamp());

        $produced = [...$messages[0]->getMessages(new MessageContext('electronic_invoicing', $messages[0]->getId(), $trigger, $first))];
        self::assertCount(1, $produced);
        self::assertInstanceOf(SyncAllPlatforms::class, $produced[0]);
    }
}
