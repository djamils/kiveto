<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Inventory\Infrastructure\Scheduler;

use App\Context\Inventory\Application\Command\NotifyExpiringLots\NotifyExpiringLots;
use App\Context\Inventory\Infrastructure\Scheduler\InventoryScheduleProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Scheduler\Generator\MessageContext;

final class InventoryScheduleProviderTest extends TestCase
{
    public function testTheExpiringLotsAreScannedEveryDayAt0713ParisUnderALock(): void
    {
        $lockFactory = new LockFactory(new InMemoryStore());
        $schedule    = new InventoryScheduleProvider($lockFactory)->getSchedule();

        $lock = $schedule->getLock();
        self::assertNotNull($lock);
        self::assertTrue($lock->acquire());
        self::assertFalse($lockFactory->createLock('scheduler.inventory')->acquire());

        $messages = $schedule->getRecurringMessages();
        self::assertCount(1, $messages);

        $trigger = $messages[0]->getTrigger();
        $first   = $trigger->getNextRunDate(new \DateTimeImmutable('now'));
        self::assertNotNull($first);
        self::assertSame('07:13', $first->setTimezone(new \DateTimeZone('Europe/Paris'))->format('H:i'));

        $produced = [...$messages[0]->getMessages(new MessageContext('inventory', $messages[0]->getId(), $trigger, $first))];
        self::assertCount(1, $produced);
        self::assertInstanceOf(NotifyExpiringLots::class, $produced[0]);
    }
}
