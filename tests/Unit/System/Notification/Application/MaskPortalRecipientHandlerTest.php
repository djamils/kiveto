<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Notification\Application;

use App\System\Notification\Application\Command\DeleteMessageFiles\DeleteMessageFiles;
use App\System\Notification\Application\Command\MaskPortalRecipient\MaskPortalRecipient;
use App\System\Notification\Application\Command\MaskPortalRecipient\MaskPortalRecipientHandler;
use App\System\Notification\Domain\Message\Attachment;
use App\System\Notification\Domain\Message\AttachmentOwner;
use App\System\Notification\Domain\Message\Channel;
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
        $queued   = $this->message('01960000-0000-7000-8000-000000000a03', MessagePurpose::PORTAL_OTP, Channel::SMS, self::PHONE, sent: false);
        $clinic   = $this->message('01960000-0000-7000-8000-000000000a04', MessagePurpose::PORTAL_INVITATION, Channel::SMS, self::PHONE, sent: true);
        $other    = $this->message('01960000-0000-7000-8000-000000000a05', MessagePurpose::PORTAL_OTP, Channel::SMS, '+33612340599', sent: true);
        foreach ([$otp, $notice, $queued, $clinic, $other] as $message) {
            $messages->save($message);
        }

        $bus     = new CapturingMessageBus();
        $handler = new MaskPortalRecipientHandler($messages, $bus, new FrozenClock(new \DateTimeImmutable('2026-11-01 15:00:00')));

        self::assertSame(2, $handler(new MaskPortalRecipient(self::PHONE, self::EMAIL)));
        self::assertSame([null, null], [$otp->recipient(), $notice->recipient()]);
        self::assertNotNull($notice->contentPurgedAt());
        self::assertSame([self::PHONE, self::PHONE, '+33612340599'], [$queued->recipient()?->address, $clinic->recipient()?->address, $other->recipient()?->address], 'a queued code still goes out; the invitation is the clinic\'s message');
        [$job] = $bus->dispatched[0];
        self::assertInstanceOf(DeleteMessageFiles::class, $job);
        self::assertSame(['notification/attachments/export/notice.pdf'], $job->storageKeys);

        self::assertSame(0, $handler(new MaskPortalRecipient(self::PHONE)), 'nothing twice');
        self::assertCount(1, $bus->dispatched);
    }

    public function testThePortalAccountPurposesAreKivetosOwnMessagesToTheOwner(): void
    {
        $portal = array_values(array_filter(MessagePurpose::cases(), static fn (MessagePurpose $p): bool => $p->isPortalAccountMessage()));

        self::assertSame([MessagePurpose::PORTAL_OTP, MessagePurpose::PORTAL_EMAIL_VERIFICATION, MessagePurpose::PORTAL_EMAIL_CHANGED_NOTICE, MessagePurpose::PORTAL_DATA_EXPORT_READY, MessagePurpose::PORTAL_BOOKING_DECLINED], $portal);
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
