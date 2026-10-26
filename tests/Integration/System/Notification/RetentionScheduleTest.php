<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\Notification;

use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Notification\Application\Command\PurgeInAppNotifications\PurgeInAppNotifications;
use App\System\Notification\Application\Command\PurgeMessageContents\PurgeMessageContents;
use App\System\Notification\Application\Command\PurgeUnmatchedDeliveryEvents\PurgeUnmatchedDeliveryEvents;
use App\System\Notification\Application\Command\RematchDeliveryEvents\RematchDeliveryEvents;
use App\System\Notification\Domain\Inbox\InAppKind;
use App\System\Notification\Domain\Inbox\InAppNotification;
use App\System\Notification\Domain\Inbox\InAppNotificationId;
use App\System\Notification\Domain\Inbox\InAppNotificationRepositoryInterface;
use App\System\Notification\Domain\Message\OutboundMessageRepositoryInterface;
use App\Tests\Support\ScheduleProviders;
use App\Tests\Unit\System\Notification\Support\Messages;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RetentionScheduleTest extends KernelTestCase
{
    public function testTheNotificationScheduleRunsTheRetentionTasks(): void
    {
        $providers = self::getContainer()->get(ScheduleProviders::class);
        \assert($providers instanceof ScheduleProviders);
        $schedule = $providers->all()['notification'] ?? null;
        self::assertNotNull($schedule);

        $runs = [];
        foreach ($schedule->getSchedule()->getRecurringMessages() as $recurring) {
            $messages = iterator_to_array($recurring->getMessages(new \Symfony\Component\Scheduler\Generator\MessageContext('notification', $recurring->getId(), $recurring->getTrigger(), new \DateTimeImmutable())), false);
            $next     = $recurring->getTrigger()->getNextRunDate(new \DateTimeImmutable('2026-10-21T12:00:00+02:00'));
            self::assertNotNull($next);
            $runs[$messages[0]::class] = $next->setTimezone(new \DateTimeZone('Europe/Paris'))->format('H:i');
        }

        self::assertSame(['03:41', '03:47', '03:53'], [$runs[PurgeInAppNotifications::class], $runs[PurgeMessageContents::class], $runs[PurgeUnmatchedDeliveryEvents::class]]);
        self::assertArrayHasKey(RematchDeliveryEvents::class, $runs);
    }

    public function testThePurgesRunThroughTheBus(): void
    {
        $inbox = self::getContainer()->get('test.' . InAppNotificationRepositoryInterface::class);
        \assert($inbox instanceof InAppNotificationRepositoryInterface);
        $inbox->add(InAppNotification::post(InAppNotificationId::fromString('01960000-0000-7000-8000-0000000000b9'), Messages::CLINIC, Messages::CLIENT, InAppKind::EXPIRING_LOTS, 'Péremption proche', '', 'clinic_stock', [], null, 'expiring-lot:l-1', new \DateTimeImmutable('-31 days')));
        $messages = self::getContainer()->get('test.' . OutboundMessageRepositoryInterface::class);
        \assert($messages instanceof OutboundMessageRepositoryInterface);
        $old = Messages::email(at: new \DateTimeImmutable('-25 months'));
        $old->markSent('b-old', new \DateTimeImmutable('-25 months'));
        $messages->save($old);
        $messages->save(Messages::sms(at: new \DateTimeImmutable('-1 day')));

        $bus = self::getContainer()->get(CommandBusInterface::class);
        \assert($bus instanceof CommandBusInterface);

        self::assertSame(1, $bus->dispatch(new PurgeInAppNotifications()));
        self::assertSame(1, $bus->dispatch(new PurgeMessageContents()));
        self::assertNull($messages->get($old->id())->recipient());
        self::assertSame(0, $bus->dispatch(new RematchDeliveryEvents()));
        self::assertSame(0, $bus->dispatch(new PurgeUnmatchedDeliveryEvents()));
    }
}
