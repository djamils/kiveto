<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Infrastructure\Scheduler;

use App\Context\ClientPortal\Application\Command\ReconcilePublishedDocuments\ReconcilePublishedDocuments;
use App\Context\ClientPortal\Application\Command\SweepExpiredDataExports\SweepExpiredDataExports;
use App\Context\ClientPortal\Infrastructure\Scheduler\ClientPortalScheduleProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Scheduler\Generator\MessageContext;

#[CoversClass(ClientPortalScheduleProvider::class)]
final class ClientPortalScheduleProviderTest extends TestCase
{
    public function testScheduleIsLockedReconcilesThePublishedDocumentsAndSweepsTheExportsNightly(): void
    {
        $lockFactory = new LockFactory(new InMemoryStore());
        $schedule    = new ClientPortalScheduleProvider($lockFactory)->getSchedule();

        $lock = $schedule->getLock();
        self::assertNotNull($lock);
        self::assertTrue($lock->acquire());
        self::assertFalse(
            $lockFactory->createLock('scheduler.client_portal')->acquire(),
            'The lock comes from the injected factory.',
        );

        self::assertNull($schedule->getState());

        $messages = $schedule->getRecurringMessages();
        self::assertCount(2, $messages);

        $trigger = $messages[0]->getTrigger();
        $first   = $trigger->getNextRunDate(new \DateTimeImmutable('2026-10-29 09:00:00'));
        self::assertNotNull($first);
        $second = $trigger->getNextRunDate($first);
        self::assertNotNull($second);
        self::assertSame(300, $second->getTimestamp() - $first->getTimestamp());

        $context  = new MessageContext('client_portal', $messages[0]->getId(), $trigger, $first);
        $produced = [...$messages[0]->getMessages($context)];
        self::assertCount(1, $produced);
        self::assertInstanceOf(ReconcilePublishedDocuments::class, $produced[0]);

        // Nightly at 03:23 Paris time (a DST change shifts it by an hour until the next night).
        $noon  = new \DateTimeImmutable('tomorrow 12:00', new \DateTimeZone('Europe/Paris'));
        $sweep = $messages[1]->getTrigger()->getNextRunDate($noon);
        self::assertNotNull($sweep);
        self::assertSame('23', $sweep->format('i'));
        self::assertLessThanOrEqual(86_400, $sweep->getTimestamp() - $noon->getTimestamp());
        $sweeps = [...$messages[1]->getMessages(new MessageContext('client_portal', $messages[1]->getId(), $messages[1]->getTrigger(), $sweep))];
        self::assertInstanceOf(SweepExpiredDataExports::class, $sweeps[0] ?? null);
    }
}
