<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Scheduler;

use App\Context\Billing\Application\Command\ReconcileProfessionalTransmissions\ReconcileProfessionalTransmissions;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * Reconciles professional transmissions every hour at minute 23. The tick is
 * locked in the database; the schedule keeps no state.
 */
#[AsSchedule('billing')]
final readonly class BillingScheduleProvider implements ScheduleProviderInterface
{
    public function __construct(private LockFactory $lockFactory)
    {
    }

    public function getSchedule(): Schedule
    {
        return new Schedule()
            ->lock($this->lockFactory->createLock('scheduler.billing'))
            ->add(RecurringMessage::every(
                '1 hour',
                new ReconcileProfessionalTransmissions(),
                from: new \DateTimeImmutable('today 00:23', new \DateTimeZone('Europe/Paris')),
            ))
        ;
    }
}
