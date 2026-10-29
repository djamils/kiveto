<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Notification\Application;

use App\System\Notification\Application\Command\RecordDeliveryEvent\RecordDeliveryEvent;
use App\System\Notification\Application\Command\RecordDeliveryEvent\RecordDeliveryEventHandler;
use App\System\Notification\Domain\Event\OutboundMessageStatusChanged;
use App\System\Notification\Domain\Message\Channel;
use App\System\Notification\Domain\Message\DeliveryStatus;
use App\System\Notification\Domain\Message\OutboundMessageId;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\System\Notification\Support\CapturingBuses;
use App\Tests\Unit\System\Notification\Support\InMemoryMessages;
use App\Tests\Unit\System\Notification\Support\InMemoryUnmatchedEvents;
use App\Tests\Unit\System\Notification\Support\Messages;
use App\Tests\Unit\System\Notification\Support\SequentialUuids;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ErrorHandler\BufferingLogger;

final class RecordDeliveryEventHandlerTest extends TestCase
{
    private InMemoryMessages $messages;
    private InMemoryUnmatchedEvents $unmatched;
    private CapturingBuses $buses;
    private BufferingLogger $logger;
    private RecordDeliveryEventHandler $handler;

    protected function setUp(): void
    {
        $this->messages  = new InMemoryMessages();
        $this->unmatched = new InMemoryUnmatchedEvents($this->messages);
        $this->buses     = new CapturingBuses();
        $this->logger    = new BufferingLogger();
        $this->handler   = new RecordDeliveryEventHandler($this->messages, $this->unmatched, $this->buses->publisher(), new SequentialUuids(), new FrozenClock(new \DateTimeImmutable('2026-10-21T09:32:00+00:00')), $this->logger);

        $sms = Messages::sms();
        $sms->markSent('brevo-42', new \DateTimeImmutable('2026-10-21T09:31:00+00:00'));
        self::assertNotEmpty($sms->pullDomainEvents());
        $this->messages->save($sms);
    }

    /**
     * AC 11: a delivered SMS event makes the message DELIVERED, once.
     */
    public function testAnEventIsAppliedOnce(): void
    {
        ($this->handler)(new RecordDeliveryEvent('SMS', 'brevo-42', 'DELIVERED', '2026-10-21T09:31:30+00:00'));
        ($this->handler)(new RecordDeliveryEvent('SMS', 'brevo-42', 'DELIVERED', '2026-10-21T09:31:30+00:00'));

        self::assertSame(DeliveryStatus::DELIVERED, $this->messages->get(OutboundMessageId::fromString('01960000-0000-7000-8000-000000000501'))->status());
        self::assertCount(1, $this->buses->eventsOf(OutboundMessageStatusChanged::class));
    }

    /**
     * AC 11: an event for an id not stored yet is kept (and logged) for the replay.
     */
    public function testAnEventForAnUnknownIdIsKept(): void
    {
        ($this->handler)(new RecordDeliveryEvent('EMAIL', 'brevo-42', 'BOUNCED', '2026-10-21T09:31:30+00:00', 'hard_bounce'));

        $kept = array_values($this->unmatched->events);
        self::assertCount(1, $kept);
        self::assertSame([Channel::EMAIL, 'brevo-42', 'BOUNCED', 'hard_bounce', '2026-10-21T09:32:00+00:00'], [$kept[0]->channel, $kept[0]->externalId, $kept[0]->event->kind(), $kept[0]->event->reason, $kept[0]->receivedAt->format(\DATE_ATOM)]);
        $logs = $this->logger->cleanLogs();
        self::assertIsArray($logs[0]);
        self::assertSame('Delivery event kept: no message carries that provider id yet.', $logs[0][1]);
    }
}
