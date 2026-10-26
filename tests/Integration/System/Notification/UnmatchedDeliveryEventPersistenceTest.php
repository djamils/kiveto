<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\Notification;

use App\System\Notification\Domain\Delivery\DeliveryEvent;
use App\System\Notification\Domain\Delivery\UnmatchedDeliveryEvent;
use App\System\Notification\Domain\Delivery\UnmatchedDeliveryEventRepositoryInterface;
use App\System\Notification\Domain\Message\Channel;
use App\System\Notification\Domain\Message\DeliveryStatus;
use App\System\Notification\Domain\Message\OutboundMessageRepositoryInterface;
use App\Tests\Unit\System\Notification\Support\Messages;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class UnmatchedDeliveryEventPersistenceTest extends KernelTestCase
{
    public function testKeptEventsAreFoundByIdByMatchAndByAge(): void
    {
        $events = self::getContainer()->get('test.' . UnmatchedDeliveryEventRepositoryInterface::class);
        \assert($events instanceof UnmatchedDeliveryEventRepositoryInterface);
        $messages = self::getContainer()->get('test.' . OutboundMessageRepositoryInterface::class);
        \assert($messages instanceof OutboundMessageRepositoryInterface);

        $events->keep(new UnmatchedDeliveryEvent('01960000-0000-7000-8000-0000000000a1', Channel::EMAIL, '<brevo-1@relay>', DeliveryEvent::status(DeliveryStatus::BOUNCED, new \DateTimeImmutable('2026-10-21T11:31:05+02:00'), 'hard_bounce'), new \DateTimeImmutable('2026-10-21T09:31:06+00:00')));
        $events->keep(new UnmatchedDeliveryEvent('01960000-0000-7000-8000-0000000000a2', Channel::EMAIL, '<brevo-1@relay>', DeliveryEvent::complaint(new \DateTimeImmutable('2026-10-21T09:40:00+00:00')), new \DateTimeImmutable('2026-10-21T09:40:01+00:00')));
        $events->keep(new UnmatchedDeliveryEvent('01960000-0000-7000-8000-0000000000a3', Channel::SMS, '<brevo-1@relay>', DeliveryEvent::status(DeliveryStatus::DELIVERED, new \DateTimeImmutable('2026-10-20T09:00:00+00:00')), new \DateTimeImmutable('2026-10-20T09:00:01+00:00')));

        $found = $events->findFor(Channel::EMAIL, '<brevo-1@relay>');
        self::assertSame(['01960000-0000-7000-8000-0000000000a1', '01960000-0000-7000-8000-0000000000a2'], array_map(static fn (UnmatchedDeliveryEvent $e): string => $e->id, $found));
        self::assertSame([Channel::EMAIL, '<brevo-1@relay>', 'BOUNCED', 'hard_bounce', '2026-10-21T09:31:05+00:00', '2026-10-21 09:31:06'], [
            $found[0]->channel, $found[0]->externalId, $found[0]->event->kind(), $found[0]->event->reason,
            $found[0]->event->occurredAt->setTimezone(new \DateTimeZone('UTC'))->format(\DATE_ATOM), $found[0]->receivedAt->format('Y-m-d H:i:s'),
        ]);
        self::assertSame('COMPLAINT', $found[1]->event->kind());

        self::assertSame([], $events->findMatchable(10), 'no message carries that id yet');
        $message = Messages::email();
        $message->markSent('<brevo-1@relay>', new \DateTimeImmutable('2026-10-21 09:31:00'));
        $messages->save($message);
        self::assertSame(['01960000-0000-7000-8000-0000000000a1'], array_map(static fn (UnmatchedDeliveryEvent $e): string => $e->id, $events->findMatchable(1)), 'same channel only, oldest first, limited');

        self::assertSame(['01960000-0000-7000-8000-0000000000a3'], array_map(static fn (UnmatchedDeliveryEvent $e): string => $e->id, $events->findReceivedBefore(new \DateTimeImmutable('2026-10-21T00:00:00+00:00'))));

        $events->delete('01960000-0000-7000-8000-0000000000a1');
        self::assertCount(1, $events->findFor(Channel::EMAIL, '<brevo-1@relay>'));
    }
}
