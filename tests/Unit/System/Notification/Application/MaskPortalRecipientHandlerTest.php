<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Notification\Application;

use App\System\Notification\Application\Command\DeleteMessageFiles\DeleteMessageFiles;
use App\System\Notification\Application\Command\MaskPortalRecipient\MaskPortalRecipient;
use App\System\Notification\Application\Command\MaskPortalRecipient\MaskPortalRecipientHandler;
use App\System\Notification\Domain\Event\OutboundMessageStatusChanged;
use App\System\Notification\Domain\Message\Attachment;
use App\System\Notification\Domain\Message\AttachmentOwner;
use App\System\Notification\Domain\Message\Channel;
use App\System\Notification\Domain\Message\DeliveryStatus;
use App\System\Notification\Domain\Message\IdempotencyKey;
use App\System\Notification\Domain\Message\MessagePurpose;
use App\System\Notification\Domain\Message\OutboundMessage;
use App\System\Notification\Domain\Message\OutboundMessageId;
use App\System\Notification\Domain\Message\Recipient;
use App\System\Notification\Domain\Message\RenderedContent;
use App\System\Notification\Domain\Message\SenderIdentity;
use App\System\Notification\Domain\Message\SmsEncoding;
use App\System\Notification\Domain\Message\SubjectReference;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\System\Notification\Support\CapturingBuses;
use App\Tests\Unit\System\Notification\Support\CapturingMessageBus;
use App\Tests\Unit\System\Notification\Support\InMemoryMessages;
use PHPUnit\Framework\TestCase;

/** Account deletion: Kiveto's own messages to the owner lose their recipient; the clinics' stay. */
final class MaskPortalRecipientHandlerTest extends TestCase
{
    private const string PHONE = '+33612340501';
    private const string EMAIL = 'owner@example.com';

    public function testKivetoMessagesToTheOwnerAreMaskedOnce(): void
    {
        $messages = new InMemoryMessages();
        $otp      = $this->message('01960000-0000-7000-8000-000000000a01', MessagePurpose::PORTAL_OTP, Channel::SMS, self::PHONE, sent: true);
        $notice   = $this->message('01960000-0000-7000-8000-000000000a02', MessagePurpose::PORTAL_DATA_EXPORT_READY, Channel::EMAIL, self::EMAIL, sent: true, file: true);
        $clinic   = $this->message('01960000-0000-7000-8000-000000000a04', MessagePurpose::PORTAL_INVITATION, Channel::SMS, self::PHONE, sent: true);
        $other    = $this->message('01960000-0000-7000-8000-000000000a05', MessagePurpose::PORTAL_OTP, Channel::SMS, '+33612340599', sent: true);
        foreach ([$otp, $notice, $clinic, $other] as $message) {
            $messages->save($message);
        }

        $bus     = new CapturingMessageBus();
        $handler = $this->handler($messages, $bus, new CapturingBuses());

        self::assertSame(2, $handler(new MaskPortalRecipient(self::PHONE, self::EMAIL)));
        self::assertSame([null, null], [$otp->recipient(), $notice->recipient()]);
        self::assertSame([DeliveryStatus::SENT, DeliveryStatus::SENT], [$otp->status(), $notice->status()], 'a message handed over keeps its status');
        self::assertNotNull($notice->contentPurgedAt());
        self::assertSame([self::PHONE, '+33612340599'], [$clinic->recipient()?->address, $other->recipient()?->address], 'the invitation is the clinic\'s message');
        [$job] = $bus->dispatched[0];
        self::assertInstanceOf(DeleteMessageFiles::class, $job);
        self::assertSame(['notification/attachments/export/notice.pdf'], $job->storageKeys);

        self::assertSame(0, $handler(new MaskPortalRecipient(self::PHONE)), 'nothing twice');
        self::assertCount(1, $bus->dispatched);
    }

    /**
     * Given a deleted Portal account with messages still queued to its phone,
     * then non-critical ones are cancelled and never sent, and every message
     * to that phone has its recipient masked.
     */
    public function testQueuedMessagesToTheOwnerAreCancelledAndMasked(): void
    {
        $messages = new InMemoryMessages();
        $code     = $this->message('01960000-0000-7000-8000-000000000b01', MessagePurpose::PORTAL_OTP, Channel::SMS, self::PHONE, sent: false);
        $declined = $this->message('01960000-0000-7000-8000-000000000b02', MessagePurpose::PORTAL_BOOKING_DECLINED, Channel::SMS, self::PHONE, sent: false);
        $export   = $this->message('01960000-0000-7000-8000-000000000b03', MessagePurpose::PORTAL_DATA_EXPORT_READY, Channel::EMAIL, self::EMAIL, sent: false, file: true);
        $security = $this->message('01960000-0000-7000-8000-000000000b04', MessagePurpose::PORTAL_EMAIL_CHANGED_NOTICE, Channel::EMAIL, self::EMAIL, sent: false);
        $clinic   = $this->message('01960000-0000-7000-8000-000000000b05', MessagePurpose::PORTAL_INVITATION, Channel::SMS, self::PHONE, sent: false);
        foreach ([$code, $declined, $export, $security, $clinic] as $message) {
            self::assertNotSame([], $message->pullDomainEvents());
            $messages->save($message);
        }

        $bus     = new CapturingMessageBus();
        $events  = new CapturingBuses();
        $handler = $this->handler($messages, $bus, $events);

        self::assertSame(3, $handler(new MaskPortalRecipient(self::PHONE, self::EMAIL)));

        foreach ([$code, $declined, $export] as $cancelled) {
            self::assertSame(DeliveryStatus::CANCELLED, $cancelled->status());
            self::assertNull($cancelled->recipient());
            self::assertNotNull($cancelled->contentPurgedAt());
            self::assertSame(MaskPortalRecipientHandler::CANCELLED_REASON, $cancelled->history()[1]->reason);
        }
        self::assertSame('', $code->content()?->smsText, 'the code is erased');
        $announced = $events->eventsOf(OutboundMessageStatusChanged::class);
        self::assertSame([['CANCELLED', ''], ['CANCELLED', ''], ['CANCELLED', '']], array_map(static fn (OutboundMessageStatusChanged $e): array => [$e->status, $e->recipientLabel], $announced), 'announced without recipient');
        [$job] = $bus->dispatched[0];
        self::assertInstanceOf(DeleteMessageFiles::class, $job);

        self::assertSame([DeliveryStatus::QUEUED, self::EMAIL], [$security->status(), $security->recipient()?->address], 'the security notice still goes out');
        self::assertSame([DeliveryStatus::QUEUED, self::PHONE], [$clinic->status(), $clinic->recipient()?->address], 'the clinic\'s message is untouched');

        // Handed over later, the security notice is masked by a retry of the erasure.
        $security->markSent('provider-notice', new \DateTimeImmutable('2026-11-01 15:01:00'));
        self::assertSame(1, $handler(new MaskPortalRecipient(self::PHONE, self::EMAIL)));
        self::assertSame([DeliveryStatus::SENT, null], [$security->status(), $security->recipient()]);
    }

    public function testThePortalAccountPurposesAreKivetosOwnMessagesToTheOwner(): void
    {
        $portal = array_values(array_filter(MessagePurpose::cases(), static fn (MessagePurpose $p): bool => $p->isPortalAccountMessage()));

        self::assertSame([MessagePurpose::PORTAL_OTP, MessagePurpose::PORTAL_EMAIL_VERIFICATION, MessagePurpose::PORTAL_EMAIL_CHANGED_NOTICE, MessagePurpose::PORTAL_DATA_EXPORT_READY, MessagePurpose::PORTAL_BOOKING_DECLINED], $portal);

        $cancelled = array_values(array_filter(MessagePurpose::cases(), static fn (MessagePurpose $p): bool => $p->isCancelledAtAccountDeletion()));
        self::assertSame([MessagePurpose::PORTAL_OTP, MessagePurpose::PORTAL_EMAIL_VERIFICATION, MessagePurpose::PORTAL_DATA_EXPORT_READY, MessagePurpose::PORTAL_BOOKING_DECLINED], $cancelled, 'the e-mail change notice is a security alert: it still goes out');
    }

    private function handler(InMemoryMessages $messages, CapturingMessageBus $bus, CapturingBuses $events): MaskPortalRecipientHandler
    {
        return new MaskPortalRecipientHandler($messages, $bus, new FrozenClock(new \DateTimeImmutable('2026-11-01 15:00:00')), $events->publisher());
    }

    private function message(string $id, MessagePurpose $purpose, Channel $channel, string $address, bool $sent, bool $file = false): OutboundMessage
    {
        $at      = new \DateTimeImmutable('2026-10-30 10:00:00');
        $content = Channel::SMS === $channel
            ? RenderedContent::sms('Code de connexion : 123456', SmsEncoding::GSM_7, 1)
            : RenderedContent::email('Votre export est prêt', '<p>Bonjour Alice</p>', 'Bonjour Alice', null);
        $files = $file ? [Attachment::of('notification/attachments/export/notice.pdf', 'notice.pdf', 'application/pdf', AttachmentOwner::NOTIFICATION)] : [];

        $message = OutboundMessage::queue(
            OutboundMessageId::fromString($id),
            null,
            null,
            $purpose,
            $channel,
            Recipient::for($channel, $address, ''),
            SenderIdentity::kiveto('no-reply@kiveto.app'),
            SubjectReference::of('portal', $id),
            IdempotencyKey::fromString('key:' . $id),
            $content,
            $files,
            $at,
        );

        if ($sent) {
            $message->markSent('provider-' . $id, $at->modify('+1 second'));
        }

        return $message;
    }
}
