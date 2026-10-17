<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Scheduler;

use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * The `default` schedule, consumed by the `scheduler-worker` service
 * (`messenger:consume scheduler_default`).
 *
 * The schedule is locked in the database (`shared.lock.factory`) so that several
 * workers never run the same tick twice. It is deliberately NOT stateful: missed
 * runs are not replayed. Every scheduled task must therefore be idempotent and
 * catch up by itself (e.g. a period-closing task closes every ended, unclosed
 * period; a polling task resumes from its own cursor).
 */
#[AsSchedule('default')]
final readonly class DefaultScheduleProvider implements ScheduleProviderInterface
{
    public function __construct(private LockFactory $lockFactory)
    {
    }

    public function getSchedule(): Schedule
    {
        return new Schedule()
            ->lock($this->lockFactory->createLock('scheduler.default'))
            ->add(RecurringMessage::every('1 hour', new SchedulerHeartbeat()))
        ;
    }
}
