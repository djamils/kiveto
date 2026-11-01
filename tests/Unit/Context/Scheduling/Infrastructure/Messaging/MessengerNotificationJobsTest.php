<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Infrastructure\Messaging;

use App\Context\Scheduling\Application\Command\DeclineExpiredPendingAppointment\DeclineExpiredPendingAppointment;
use App\Context\Scheduling\Application\Command\PostNewAppointmentNotice\PostNewAppointmentNotice;
use App\Context\Scheduling\Application\Command\PostPendingBookingNotice\PostPendingBookingNotice;
use App\Context\Scheduling\Application\Command\RequestAppointmentReminder\RequestAppointmentReminder;
use App\Context\Scheduling\Application\Command\SyncAppointmentNotification\SyncAppointmentNotification;
use App\Context\Scheduling\Application\Command\WithdrawNewAppointmentNotice\WithdrawNewAppointmentNotice;
use App\Context\Scheduling\Application\Command\WithdrawPendingBookingNotice\WithdrawPendingBookingNotice;
use App\Context\Scheduling\Infrastructure\Messaging\MessengerNotificationJobs;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

final class MessengerNotificationJobsTest extends TestCase
{
    public function testTheSyncIsDelayedAndTheReminderRequestIsNot(): void
    {
        $bus = new class implements MessageBusInterface {
            /** @var list<Envelope> */
            public array $sent = [];

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                return $this->sent[] = Envelope::wrap($message, $stamps);
            }
        };
        $jobs = new MessengerNotificationJobs($bus, 120000);

        $jobs->syncLater('appointment-1');
        $jobs->requestReminder('appointment-2');
        $jobs->postNewAppointmentNotice('appointment-3', 'julie');
        $jobs->withdrawNewAppointmentNotice('appointment-4');
        $jobs->postPendingBookingNotice('appointment-5');
        $jobs->withdrawPendingBookingNotice('appointment-6');
        $jobs->declineExpiredPending('appointment-7');

        [$sync, $reminder, $notice, $withdrawal, $pending, $pendingWithdrawal, $decline] = $bus->sent;
        self::assertInstanceOf(SyncAppointmentNotification::class, $sync->getMessage());
        self::assertSame('appointment-1', $sync->getMessage()->appointmentId);
        self::assertSame(120000, $sync->last(DelayStamp::class)?->getDelay());

        self::assertInstanceOf(RequestAppointmentReminder::class, $reminder->getMessage());
        self::assertSame('appointment-2', $reminder->getMessage()->appointmentId);
        self::assertNull($reminder->last(DelayStamp::class));

        self::assertInstanceOf(PostNewAppointmentNotice::class, $notice->getMessage());
        self::assertSame(['appointment-3', 'julie'], [$notice->getMessage()->appointmentId, $notice->getMessage()->actorUserId]);
        self::assertInstanceOf(WithdrawNewAppointmentNotice::class, $withdrawal->getMessage());
        self::assertSame('appointment-4', $withdrawal->getMessage()->appointmentId);

        self::assertInstanceOf(PostPendingBookingNotice::class, $pending->getMessage());
        self::assertSame('appointment-5', $pending->getMessage()->appointmentId);
        self::assertInstanceOf(WithdrawPendingBookingNotice::class, $pendingWithdrawal->getMessage());
        self::assertSame('appointment-6', $pendingWithdrawal->getMessage()->appointmentId);
        self::assertInstanceOf(DeclineExpiredPendingAppointment::class, $decline->getMessage());
        self::assertSame('appointment-7', $decline->getMessage()->appointmentId);
        self::assertNull($decline->last(DelayStamp::class));
    }
}
