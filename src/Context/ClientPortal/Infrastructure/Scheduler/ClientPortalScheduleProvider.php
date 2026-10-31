<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Scheduler;

use App\Context\ClientPortal\Application\Command\ReconcilePublishedDocuments\ReconcilePublishedDocuments;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * The `client_portal` schedule, consumed by the `scheduler-worker` service
 * and locked in the database like the others. Every 5 minutes, the files
 * of published documents still expected are asked for again.
 */
#[AsSchedule('client_portal')]
final readonly class ClientPortalScheduleProvider implements ScheduleProviderInterface
{
    public function __construct(private LockFactory $lockFactory)
    {
    }

    public function getSchedule(): Schedule
    {
        return new Schedule()
            ->lock($this->lockFactory->createLock('scheduler.client_portal'))
            ->add(RecurringMessage::every('5 minutes', new ReconcilePublishedDocuments()))
        ;
    }
}
