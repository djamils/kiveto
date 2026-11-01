<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Application\EventSubscriber;

use App\Context\Scheduling\Application\EventSubscriber\NotifyPractitionerOnAppointmentScheduled;
use App\Context\Scheduling\Domain\Event\AppointmentCancelled;
use App\Context\Scheduling\Domain\Event\AppointmentConfirmed;
use App\Context\Scheduling\Domain\Event\AppointmentDeclined;
use App\Context\Scheduling\Domain\Event\AppointmentRequested;
use App\Context\Scheduling\Domain\Event\AppointmentScheduled;
use App\Tests\Unit\Context\Scheduling\Support\Appointments;
use App\Tests\Unit\Context\Scheduling\Support\CapturingJobs;
use PHPUnit\Framework\TestCase;

final class NotifyPractitionerOnAppointmentScheduledTest extends TestCase
{
    public function testABookingMadeBySomeoneElseQueuesTheNotice(): void
    {
        $jobs       = new CapturingJobs();
        $subscriber = new NotifyPractitionerOnAppointmentScheduled($jobs);

        $subscriber->onScheduled(self::scheduled('a-1', 'julie'));
        $subscriber->onScheduled(self::scheduled('a-2', null));
        $subscriber->onScheduled(self::scheduled('a-3', Appointments::ROUSSEAU));

        self::assertSame([['a-1', 'julie'], ['a-2', null]], $jobs->newAppointmentNotices);
    }

    public function testACancellationQueuesTheWithdrawal(): void
    {
        $jobs = new CapturingJobs();

        new NotifyPractitionerOnAppointmentScheduled($jobs)->onCancelled(new AppointmentCancelled(Appointments::ID, Appointments::CLINIC));

        self::assertSame([Appointments::ID], $jobs->withdrawals);
        self::assertSame([], $jobs->pendingBookingWithdrawals);
    }

    public function testAPendingRequestIsAnnouncedThenWithdrawnOnceHandled(): void
    {
        // AC 39: posted on request; withdrawn on confirm, decline or client withdrawal; no « Nouveau RDV »
        $jobs       = new CapturingJobs();
        $subscriber = new NotifyPractitionerOnAppointmentScheduled($jobs);

        $subscriber->onRequested(new AppointmentRequested('a-1', Appointments::CLINIC, null, Appointments::ROUSSEAU, '2026-10-22T07:30:00+00:00', 30, 'Vaccination', null, Appointments::OWNER, Appointments::ANIMAL, null, 'portal'));
        $subscriber->onConfirmed(new AppointmentConfirmed('a-1', Appointments::CLINIC, Appointments::ROUSSEAU, '2026-10-22T07:30:00+00:00', Appointments::OWNER, Appointments::ANIMAL, 'portal', false));
        $subscriber->onDeclined(new AppointmentDeclined('a-2', Appointments::CLINIC, Appointments::ROUSSEAU, '2026-10-22T07:30:00+00:00', Appointments::OWNER, Appointments::ANIMAL, 'portal', 'expired'));
        $subscriber->onCancelled(new AppointmentCancelled('a-3', Appointments::CLINIC, true, 'client', null, true, 'portal'));

        self::assertSame(['a-1'], $jobs->pendingBookingNotices);
        self::assertSame(['a-1', 'a-2', 'a-3'], $jobs->pendingBookingWithdrawals);
        self::assertSame([], $jobs->newAppointmentNotices);
    }

    private static function scheduled(string $id, ?string $actor): AppointmentScheduled
    {
        return new AppointmentScheduled($id, Appointments::CLINIC, null, Appointments::ROUSSEAU, '2026-10-22T07:30:00+00:00', 30, null, null, Appointments::OWNER, Appointments::ANIMAL, $actor);
    }
}
