<?php

declare(strict_types=1);

namespace App\Context\Clinic\Infrastructure\Scheduler;

use App\Context\Clinic\Application\Command\Staff\RecordVeterinarianDailyCounts\RecordVeterinarianDailyCounts;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * The `clinic` schedule, consumed by the `scheduler-worker` service and
 * locked in the database. Every day at 00:07 Paris time, the day's count of
 * active veterinarians of every clinic (D6) — so that a day without any
 * membership change still has its row. A DST change shifts the run by an
 * hour; the changes of the day count anyway.
 */
#[AsSchedule('clinic')]
final readonly class ClinicScheduleProvider implements ScheduleProviderInterface
{
    public function __construct(private LockFactory $lockFactory)
    {
    }

    public function getSchedule(): Schedule
    {
        return new Schedule()
            ->lock($this->lockFactory->createLock('scheduler.clinic'))
            ->add(RecurringMessage::every(
                '1 day',
                new RecordVeterinarianDailyCounts(),
                from: new \DateTimeImmutable('today 00:07', new \DateTimeZone('Europe/Paris')),
            ))
        ;
    }
}
