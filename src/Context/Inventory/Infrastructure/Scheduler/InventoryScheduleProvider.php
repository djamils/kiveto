<?php

declare(strict_types=1);

namespace App\Context\Inventory\Infrastructure\Scheduler;

use App\Context\Inventory\Application\Command\NotifyExpiringLots\NotifyExpiringLots;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * The `inventory` schedule, consumed by the `scheduler-worker` service and
 * locked in the database: every day at 07:13 Paris time, before the clinics
 * open, the « Péremption proche » scan of the lots.
 */
#[AsSchedule('inventory')]
final readonly class InventoryScheduleProvider implements ScheduleProviderInterface
{
    public function __construct(private LockFactory $lockFactory)
    {
    }

    public function getSchedule(): Schedule
    {
        return new Schedule()
            ->lock($this->lockFactory->createLock('scheduler.inventory'))
            ->add(RecurringMessage::every(
                '1 day',
                new NotifyExpiringLots(),
                from: new \DateTimeImmutable('today 07:13', new \DateTimeZone('Europe/Paris')),
            ))
        ;
    }
}
