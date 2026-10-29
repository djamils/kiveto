<?php

declare(strict_types=1);

namespace App\System\Notification\Infrastructure\Scheduler;

use App\System\Notification\Application\Command\PurgeInAppNotifications\PurgeInAppNotifications;
use App\System\Notification\Application\Command\PurgeMessageContents\PurgeMessageContents;
use App\System\Notification\Application\Command\PurgeUnmatchedDeliveryEvents\PurgeUnmatchedDeliveryEvents;
use App\System\Notification\Application\Command\RematchDeliveryEvents\RematchDeliveryEvents;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * Retention and webhook re-matching. Nightly purges at fixed Europe/Paris
 * times (03:41 bell entries, 03:47 message contents, 03:53 unmatched
 * webhook events); the re-matching of early webhooks every 61 s — not 60,
 * a divisor of the scheduler worker's time limit, which would restart the
 * worker on that tick each time. The tick is locked in the database so that
 * several scheduler workers never run it twice. A DST change shifts a
 * nightly run by an hour; every purge catches up the next night.
 */
#[AsSchedule('notification')]
final readonly class NotificationScheduleProvider implements ScheduleProviderInterface
{
    private const string TIME_ZONE = 'Europe/Paris';

    public function __construct(private LockFactory $lockFactory)
    {
    }

    public function getSchedule(): Schedule
    {
        return new Schedule()
            ->lock($this->lockFactory->createLock('scheduler.notification'))
            ->add(RecurringMessage::every('1 day', new PurgeInAppNotifications(), from: self::at('03:41')))
            ->add(RecurringMessage::every('1 day', new PurgeMessageContents(), from: self::at('03:47')))
            ->add(RecurringMessage::every('61 seconds', new RematchDeliveryEvents()))
            ->add(RecurringMessage::every('1 day', new PurgeUnmatchedDeliveryEvents(), from: self::at('03:53')))
        ;
    }

    private static function at(string $time): \DateTimeImmutable
    {
        return new \DateTimeImmutable('today ' . $time, new \DateTimeZone(self::TIME_ZONE));
    }
}
