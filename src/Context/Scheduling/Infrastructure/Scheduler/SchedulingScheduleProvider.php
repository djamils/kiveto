<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Scheduler;

use App\Context\Scheduling\Application\Command\RequestDueReminders\RequestDueReminders;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * The `scheduling` schedule, consumed by the `scheduler-worker` service and
 * locked in the database: every 15 minutes, on the quarter hour (Paris), the
 * scan of due appointment reminders. 900 s does not divide the worker's time
 * limit, so a worker restart never falls on the scan's tick.
 */
#[AsSchedule('scheduling')]
final readonly class SchedulingScheduleProvider implements ScheduleProviderInterface
{
    public function __construct(private LockFactory $lockFactory)
    {
    }

    public function getSchedule(): Schedule
    {
        return new Schedule()
            ->lock($this->lockFactory->createLock('scheduler.scheduling'))
            ->add(RecurringMessage::every(
                '15 minutes',
                new RequestDueReminders(),
                from: new \DateTimeImmutable('today 00:00', new \DateTimeZone('Europe/Paris')),
            ))
        ;
    }
}
