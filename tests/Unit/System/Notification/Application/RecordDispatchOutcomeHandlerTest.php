<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Notification\Application;

use App\System\Notification\Application\Command\RecordDispatchOutcome\RecordDispatchOutcome;
use App\System\Notification\Application\Command\RecordDispatchOutcome\RecordDispatchOutcomeHandler;
use App\System\Notification\Application\Service\DeliveryEventReplayer;
use App\System\Notification\Domain\Delivery\DeliveryEvent;
use App\System\Notification\Domain\Delivery\UnmatchedDeliveryEvent;
use App\System\Notification\Domain\Event\OutboundMessageStatusChanged;
use App\System\Notification\Domain\Message\Channel;
use App\System\Notification\Domain\Message\DeliveryStatus;
use App\System\Notification\Domain\Message\OutboundMessageId;
use App\Tests\Unit\System\Notification\Support\CapturingBuses;
use App\Tests\Unit\System\Notification\Support\InMemoryMessages;
use App\Tests\Unit\System\Notification\Support\InMemoryUnmatchedEvents;
use App\Tests\Unit\System\Notification\Support\Messages;
use PHPUnit\Framework\TestCase;

final class RecordDispatchOutcomeHandlerTest extends TestCase
{
    private const string SMS_ID = '01960000-0000-7000-8000-000000000501';

    private InMemoryMessages $messages;
    private InMemoryUnmatchedEvents $unmatched;
    private CapturingBuses $buses;
    private RecordDispatchOutcomeHandler $handler;

    protected function setUp(): void
    {
        $this->messages  = new InMemoryMessages();
        $this->unmatched = new InMemoryUnmatchedEvents($this->messages);
        $this->buses     = new CapturingBuses();
        $this->handler   = new RecordDispatchOutcomeHandler(
            $this->messages,
            $this->unmatched,
            new DeliveryEventReplayer($this->messages, $this->unmatched, $this->buses->publisher()),
            $this->buses->publisher(),
        );
        $sms = Messages::sms();
        self::assertNotEmpty($sms->pullDomainEvents());
        $this->messages->save($sms);
    }

    /**
     * AC 2: the message becomes SENT with the provider message id.
     */
    public function testTheProviderIdIsRecorded(): void
    {
        ($this->handler)(new RecordDispatchOutcome(self::SMS_ID, 'brevo-42', null, '2026-10-21T09:31:00+00:00'));

        $message = $this->messages->get(OutboundMessageId::fromString(self::SMS_ID));
        self::assertSame(DeliveryStatus::SENT, $message->status());
        self::assertSame('brevo-42', $message->externalMessageId());
        self::assertSame('2026-10-21T09:31:00+00:00', $message->sentAt()?->format(\DATE_ATOM));
        self::assertSame(['SENT'], array_map(static fn (OutboundMessageStatusChanged $e): string => $e->status, $this->buses->eventsOf(OutboundMessageStatusChanged::class)));
    }

    /**
     * AC 11: a provider event that came before the id is applied now, then dropped;
     * the events of other ids stay kept.
     */
    public function testTheEventsThatArrivedFirstAreReplayed(): void
    {
        $this->unmatched->keep(new UnmatchedDeliveryEvent('u-1', Channel::SMS, 'brevo-42', DeliveryEvent::status(DeliveryStatus::DELIVERED, new \DateTimeImmutable('2026-10-21T09:31:05+00:00')), new \DateTimeImmutable('2026-10-21T09:31:06+00:00')));
        $this->unmatched->keep(new UnmatchedDeliveryEvent('u-2', Channel::SMS, 'brevo-42', DeliveryEvent::complaint(new \DateTimeImmutable('2026-10-21T09:40:00+00:00')), new \DateTimeImmutable('2026-10-21T09:40:01+00:00')));
        $this->unmatched->keep(new UnmatchedDeliveryEvent('u-3', Channel::SMS, 'brevo-42', DeliveryEvent::status(DeliveryStatus::DELIVERED, new \DateTimeImmutable('2026-10-21T09:31:05+00:00')), new \DateTimeImmutable('2026-10-21T09:41:00+00:00')));
        $this->unmatched->keep(new UnmatchedDeliveryEvent('u-4', Channel::EMAIL, 'brevo-42', DeliveryEvent::status(DeliveryStatus::BOUNCED, new \DateTimeImmutable('2026-10-21T09:31:05+00:00')), new \DateTimeImmutable('2026-10-21T09:31:06+00:00')));

        ($this->handler)(new RecordDispatchOutcome(self::SMS_ID, 'brevo-42', null, '2026-10-21T09:31:00+00:00'));

        $message = $this->messages->get(OutboundMessageId::fromString(self::SMS_ID));
        self::assertSame(DeliveryStatus::DELIVERED, $message->status());
        self::assertNotNull($message->complainedAt());
        self::assertSame(['SENT', 'DELIVERED'], array_map(static fn (OutboundMessageStatusChanged $e): string => $e->status, $this->buses->eventsOf(OutboundMessageStatusChanged::class)));
        self::assertSame(['u-4'], array_keys($this->unmatched->events), 'the duplicate is dropped too; another channel is not this message');
    }

    /**
     * AC 4: a refusal or exhausted retries leave the message FAILED with the reason.
     */
    public function testAFailureIsRecordedWithItsReason(): void
    {
        ($this->handler)(new RecordDispatchOutcome(self::SMS_ID, null, 'Échec après plusieurs tentatives', '2026-10-21T10:00:00+00:00'));

        $message = $this->messages->get(OutboundMessageId::fromString(self::SMS_ID));
        self::assertSame(DeliveryStatus::FAILED, $message->status());
        self::assertSame('Échec après plusieurs tentatives', $message->history()[1]->reason);
    }

    public function testAFailureWithoutReasonGetsTheDefaultOne(): void
    {
        ($this->handler)(new RecordDispatchOutcome(self::SMS_ID, null, null, '2026-10-21T10:00:00+00:00'));

        self::assertSame(RecordDispatchOutcomeHandler::DEFAULT_ERROR, $this->messages->get(OutboundMessageId::fromString(self::SMS_ID))->history()[1]->reason);
    }

    public function testAReplayedOutcomeChangesNothing(): void
    {
        ($this->handler)(new RecordDispatchOutcome(self::SMS_ID, 'brevo-42', null, '2026-10-21T09:31:00+00:00'));
        ($this->handler)(new RecordDispatchOutcome(self::SMS_ID, 'brevo-43', null, '2026-10-21T09:32:00+00:00'));
        ($this->handler)(new RecordDispatchOutcome(self::SMS_ID, null, 'late failure', '2026-10-21T09:33:00+00:00'));

        $message = $this->messages->get(OutboundMessageId::fromString(self::SMS_ID));
        self::assertSame(['brevo-42', DeliveryStatus::SENT], [$message->externalMessageId(), $message->status()]);
        self::assertCount(1, $this->buses->eventsOf(OutboundMessageStatusChanged::class));
    }

    public function testAnEventWhoseIdIsStillUnknownStaysKept(): void
    {
        $kept = new UnmatchedDeliveryEvent('u-9', Channel::SMS, 'brevo-unknown', DeliveryEvent::status(DeliveryStatus::DELIVERED, new \DateTimeImmutable('2026-10-21T09:31:05+00:00')), new \DateTimeImmutable('2026-10-21T09:31:06+00:00'));
        $this->unmatched->keep($kept);

        self::assertSame(0, new DeliveryEventReplayer($this->messages, $this->unmatched, $this->buses->publisher())->replay([$kept]));
        self::assertSame(['u-9'], array_keys($this->unmatched->events));
    }
}
