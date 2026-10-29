<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Application\EventSubscriber;

use App\Context\Scheduling\Application\EventSubscriber\ScheduleAppointmentNotificationSync;
use App\Context\Scheduling\Domain\Event\AppointmentCancelled;
use App\Context\Scheduling\Domain\Event\AppointmentOwnerChanged;
use App\Context\Scheduling\Domain\Event\AppointmentPractitionerAssigneeChanged;
use App\Context\Scheduling\Domain\Event\AppointmentRescheduled;
use App\Context\Scheduling\Domain\Event\AppointmentScheduled;
use App\Tests\Unit\Context\Scheduling\Support\Appointments;
use App\Tests\Unit\Context\Scheduling\Support\CapturingJobs;
use App\Tests\Unit\Context\Scheduling\Support\InMemoryAppointments;
use App\Tests\Unit\Context\Scheduling\Support\InMemoryNotificationStates;
use PHPUnit\Framework\TestCase;

final class ScheduleAppointmentNotificationSyncTest extends TestCase
{
    public function testEveryAppointmentEventQueuesTheSync(): void
    {
        $jobs       = new CapturingJobs();
        $states     = new InMemoryNotificationStates(new InMemoryAppointments());
        $subscriber = new ScheduleAppointmentNotificationSync($jobs, $states);

        $subscriber->onScheduled(new AppointmentScheduled('a-1', Appointments::CLINIC, null, Appointments::ROUSSEAU, '2026-10-22T07:30:00+00:00', 30, null, null));
        $subscriber->onRescheduled(new AppointmentRescheduled('a-2', Appointments::CLINIC, '2026-10-22T07:30:00+00:00', 30, '2026-10-23T09:00:00+00:00', 30));
        $subscriber->onPractitionerChanged(new AppointmentPractitionerAssigneeChanged('a-3', Appointments::CLINIC, Appointments::ROUSSEAU, Appointments::LAMBERT));
        $subscriber->onOwnerChanged(new AppointmentOwnerChanged('a-4', Appointments::CLINIC, null, Appointments::OWNER));
        $subscriber->onCancelled(new AppointmentCancelled(Appointments::ID, Appointments::CLINIC));

        self::assertSame(['a-1', 'a-2', 'a-3', 'a-4', Appointments::ID], $jobs->syncs);
        self::assertSame([], $states->states);
    }

    public function testACancellationNotToBeToldSilencesTheAppointmentFirst(): void
    {
        $jobs   = new CapturingJobs();
        $states = new InMemoryNotificationStates(new InMemoryAppointments());

        new ScheduleAppointmentNotificationSync($jobs, $states)->onCancelled(new AppointmentCancelled(Appointments::ID, Appointments::CLINIC, notifyClient: false));

        self::assertTrue($states->of(Appointments::ID)->isSilenced());
        self::assertSame([Appointments::ID], $jobs->syncs);
    }
}
