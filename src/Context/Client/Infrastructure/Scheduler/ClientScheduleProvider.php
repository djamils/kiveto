<?php

declare(strict_types=1);

namespace App\Context\Client\Infrastructure\Scheduler;

use App\Context\Client\Application\Command\ReverifyStaleTaxProfiles\ReverifyStaleTaxProfiles;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * The `client` schedule, consumed by the `scheduler-worker` service
 * (`messenger:consume scheduler_default scheduler_client`), locked in the
 * database like the default one and not stateful.
 *
 * Every day at 02:17 Paris time (no cron library: a daily period from that
 * time). A DST change shifts the run by an hour and a worker restart landing
 * on 02:17 skips the day: both are caught up the next night, since the task
 * re-requests every stale profile.
 */
#[AsSchedule('client')]
final readonly class ClientScheduleProvider implements ScheduleProviderInterface
{
    public function __construct(private LockFactory $lockFactory)
    {
    }

    public function getSchedule(): Schedule
    {
        return new Schedule()
            ->lock($this->lockFactory->createLock('scheduler.client'))
            ->add(RecurringMessage::every(
                '1 day',
                new ReverifyStaleTaxProfiles(),
                from: new \DateTimeImmutable('today 02:17', new \DateTimeZone('Europe/Paris')),
            ))
        ;
    }
}
