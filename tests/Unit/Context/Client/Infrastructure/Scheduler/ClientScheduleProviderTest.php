<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Infrastructure\Scheduler;

use App\Context\Client\Application\Command\ReverifyStaleTaxProfiles\ReverifyStaleTaxProfiles;
use App\Context\Client\Infrastructure\Scheduler\ClientScheduleProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Scheduler\Generator\MessageContext;

#[CoversClass(ClientScheduleProvider::class)]
final class ClientScheduleProviderTest extends TestCase
{
    public function testScheduleIsLockedNotStatefulAndReverifiesEveryNightAt0217(): void
    {
        $lockFactory = new LockFactory(new InMemoryStore());
        $schedule    = new ClientScheduleProvider($lockFactory)->getSchedule();

        $lock = $schedule->getLock();
        self::assertNotNull($lock);
        self::assertTrue($lock->acquire());
        self::assertFalse($lockFactory->createLock('scheduler.client')->acquire(), 'The lock comes from the injected factory.');

        self::assertNull($schedule->getState());

        $messages = $schedule->getRecurringMessages();
        self::assertCount(1, $messages);

        $trigger = $messages[0]->getTrigger();
        $first   = $trigger->getNextRunDate(new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')));
        self::assertNotNull($first);
        self::assertSame('02:17', $first->setTimezone(new \DateTimeZone('Europe/Paris'))->format('H:i'));
        $second = $trigger->getNextRunDate($first);
        self::assertNotNull($second);
        self::assertSame(86400, $second->getTimestamp() - $first->getTimestamp());

        $context  = new MessageContext('client', $messages[0]->getId(), $trigger, $first);
        $produced = [...$messages[0]->getMessages($context)];
        self::assertCount(1, $produced);
        self::assertInstanceOf(ReverifyStaleTaxProfiles::class, $produced[0]);
    }
}
