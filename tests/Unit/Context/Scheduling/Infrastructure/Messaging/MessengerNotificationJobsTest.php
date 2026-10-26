<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Infrastructure\Messaging;

use App\Context\Scheduling\Application\Command\RequestAppointmentReminder\RequestAppointmentReminder;
use App\Context\Scheduling\Application\Command\SyncAppointmentNotification\SyncAppointmentNotification;
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

        [$sync, $reminder] = $bus->sent;
        self::assertInstanceOf(SyncAppointmentNotification::class, $sync->getMessage());
        self::assertSame('appointment-1', $sync->getMessage()->appointmentId);
        self::assertSame(120000, $sync->last(DelayStamp::class)?->getDelay());

        self::assertInstanceOf(RequestAppointmentReminder::class, $reminder->getMessage());
        self::assertSame('appointment-2', $reminder->getMessage()->appointmentId);
        self::assertNull($reminder->last(DelayStamp::class));
    }
}
