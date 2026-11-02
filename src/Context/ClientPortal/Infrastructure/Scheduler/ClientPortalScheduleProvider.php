<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Scheduler;

use App\Context\ClientPortal\Application\Command\LockFrozenQuestionnaires\LockFrozenQuestionnaires;
use App\Context\ClientPortal\Application\Command\ReconcilePublishedDocuments\ReconcilePublishedDocuments;
use App\Context\ClientPortal\Application\Command\SweepExpiredDataExports\SweepExpiredDataExports;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * The `client_portal` schedule, consumed by the `scheduler-worker` service
 * and locked in the database like the others. Every 5 minutes, the files
 * of published documents still expected are asked for again and the
 * questionnaires whose visit started are locked; every night
 * (Europe/Paris), the data export archives past their 48 hours whose
 * delayed deletion was lost are deleted.
 */
#[AsSchedule('client_portal')]
final readonly class ClientPortalScheduleProvider implements ScheduleProviderInterface
{
    private const string TIME_ZONE = 'Europe/Paris';

    public function __construct(private LockFactory $lockFactory)
    {
    }

    public function getSchedule(): Schedule
    {
        return new Schedule()
            ->lock($this->lockFactory->createLock('scheduler.client_portal'))
            ->add(RecurringMessage::every('5 minutes', new ReconcilePublishedDocuments()))
            ->add(RecurringMessage::every('1 day', new SweepExpiredDataExports(), from: new \DateTimeImmutable('today 03:23', new \DateTimeZone(self::TIME_ZONE))))
            ->add(RecurringMessage::every('5 minutes', new LockFrozenQuestionnaires()))
        ;
    }
}
