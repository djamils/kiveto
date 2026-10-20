<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Infrastructure\Scheduler;

use App\System\ElectronicInvoicing\Application\Command\SyncAllPlatforms\SyncAllPlatforms;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * Synchronises the platforms every five minutes. The tick is locked in the
 * database so that several scheduler workers never run it twice; the schedule
 * keeps no state of its own.
 */
#[AsSchedule('electronic_invoicing')]
final readonly class ElectronicInvoicingScheduleProvider implements ScheduleProviderInterface
{
    public function __construct(private LockFactory $lockFactory)
    {
    }

    public function getSchedule(): Schedule
    {
        return new Schedule()
            ->lock($this->lockFactory->createLock('scheduler.electronic_invoicing'))
            ->add(RecurringMessage::every('5 minutes', new SyncAllPlatforms()))
        ;
    }
}
