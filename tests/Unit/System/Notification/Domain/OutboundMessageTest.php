<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Notification\Domain;

use App\System\Notification\Domain\Event\OutboundMessageQueued;
use App\System\Notification\Domain\Event\OutboundMessageStatusChanged;
use App\System\Notification\Domain\Message\Channel;
use App\System\Notification\Domain\Message\Criticality;
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
use App\Tests\Unit\System\Notification\Support\Messages;
use PHPUnit\Framework\TestCase;

final class OutboundMessageTest extends TestCase
{
    public function testAQueuedMessageIsJournaledAndAnnounced(): void
    {
        $message = Messages::email();

        self::assertSame(DeliveryStatus::QUEUED, $message->status());
        self::assertSame(Criticality::CRITICAL, $message->criticality());
        self::assertSame(Channel::EMAIL, $message->channel());
        self::assertSame(MessagePurpose::INVOICE_SENT, $message->purpose());
        self::assertSame(Messages::CLINIC, $message->clinicId());
        self::assertSame(Messages::CLIENT, $message->clientId());
        self::assertSame('Thomas Lefebvre', $message->recipient()?->label());
        self::assertSame('clinique-montmartre@mail.kiveto.app', $message->sender()?->emailAddress);
        self::assertSame('invoice', $message->subject()->type);
        self::assertSame('invoice:1:send:1', $message->idempotencyKey()->toString());
        self::assertSame('Votre facture FA-2026-0814 — Clinique Montmartre', $message->subjectLine());
        self::assertCount(1, $message->attachments());
        self::assertSame(0, $message->segments());
        self::assertNull($message->sentAt());
        self::assertNull($message->externalMessageId());
        self::assertNull($message->complainedAt());
        self::assertNull($message->contentPurgedAt());
        self::assertSame('2026-10-16 08:00:00', $message->createdAt()->format('Y-m-d H:i:s'));
        self::assertSame('2026-10-16 08:00:00', $message->statusChangedAt()->format('Y-m-d H:i:s'));
        self::assertSame(1, $message->sequence());

        $events = $message->pullDomainEvents();
        self::assertCount(2, $events);
        self::assertInstanceOf(OutboundMessageQueued::class, $events[0]);
        self::assertInstanceOf(OutboundMessageStatusChanged::class, $events[1]);
        self::assertSame('QUEUED', $events[1]->status);
        self::assertSame('En cours', $events[1]->statusLabel);
        self::assertSame('Thomas Lefebvre', $events[1]->recipientLabel);
        self::assertSame(1, $events[1]->sequence);
    }

    public function testAnSmsSubjectLineIsItsPurpose(): void
    {
        $message = Messages::sms(segments: 2);

        self::assertSame('Rappel de rendez-vous', $message->subjectLine());
        self::assertSame(2, $message->segments());
        self::assertSame(Criticality::NON_CRITICAL, $message->criticality());
    }

    public function testTheContentMustFitTheChannel(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        OutboundMessage::queue(
            OutboundMessageId::fromString('01960000-0000-7000-8000-000000000e09'),
            null,
            null,
            MessagePurpose::TEST,
            Channel::SMS,
            Recipient::for(Channel::SMS, '+33612345678', ''),
            SenderIdentity::kiveto('no-reply@kiveto.app'),
            SubjectReference::of('test', 't1'),
            IdempotencyKey::fromString('test:1'),
            RenderedContent::email('S', '<p>x</p>', 'x'),
            [],
            new \DateTimeImmutable(),
        );
    }

    public function testAnSmsCarriesNoAttachment(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('An SMS carries no attachment.');

        OutboundMessage::queue(
            OutboundMessageId::fromString('01960000-0000-7000-8000-000000000e09'),
            null,
            null,
            MessagePurpose::TEST,
            Channel::SMS,
            Recipient::for(Channel::SMS, '+33612345678', ''),
            SenderIdentity::kiveto('no-reply@kiveto.app'),
            SubjectReference::of('test', 't1'),
            IdempotencyKey::fromString('test:1'),
            RenderedContent::sms('x', SmsEncoding::GSM_7, 1),
            Messages::email()->attachments(),
            new \DateTimeImmutable(),
        );
    }

    public function testACappedSmsIsBlockedAndNeverBilled(): void
    {
        $message = OutboundMessage::blockedByCap(
            OutboundMessageId::fromString('01960000-0000-7000-8000-000000000b01'),
            Messages::CLINIC,
            null,
            MessagePurpose::INVOICE_REMINDER,
            Recipient::for(Channel::SMS, '+33612345678', 'Claire Rey'),
            SenderIdentity::clinic('Clinique Montmartre', 'clinique-montmartre', 'mail.kiveto.app', '', ''),
            SubjectReference::of('invoice', 'inv-2'),
            IdempotencyKey::fromString('invoice:2:reminder:1:SMS'),
            RenderedContent::sms('Rappel', SmsEncoding::GSM_7, 1),
            new \DateTimeImmutable('2026-10-21 09:00:00'),
        );

        self::assertSame(DeliveryStatus::BLOCKED_CAP, $message->status());
        $sender = $message->sender();
        self::assertNotNull($sender);
        self::assertSame('Kiveto', $sender->smsSender);
        self::assertNull($sender->replyTo);
        self::assertNull($message->sentAt());
        self::assertSame('Plafond de dépassement SMS atteint', $message->history()[0]->reason);
        self::assertFalse($message->markSent('ext-1', new \DateTimeImmutable()));
        self::assertFalse($message->recordStatus(DeliveryStatus::DELIVERED, new \DateTimeImmutable(), OutboundMessage::SOURCE_PROVIDER));

        $events = $message->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(OutboundMessageStatusChanged::class, $events[0]);
        self::assertSame('Bloqué (plafond)', $events[0]->statusLabel);
    }

    public function testOnlyANonCriticalSmsCanBeCapped(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        OutboundMessage::blockedByCap(
            OutboundMessageId::fromString('01960000-0000-7000-8000-000000000b01'),
            Messages::CLINIC,
            null,
            MessagePurpose::APPOINTMENT_CONFIRMATION,
            Recipient::for(Channel::SMS, '+33612345678', 'Claire Rey'),
            SenderIdentity::kiveto('no-reply@kiveto.app'),
            SubjectReference::of('appointment', 'a'),
            IdempotencyKey::fromString('appointment:a:confirmation:1'),
            RenderedContent::sms('x', SmsEncoding::GSM_7, 1),
            new \DateTimeImmutable(),
        );
    }

    public function testAnEmailCannotBeCapped(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        OutboundMessage::blockedByCap(
            OutboundMessageId::fromString('01960000-0000-7000-8000-000000000b01'),
            Messages::CLINIC,
            null,
            MessagePurpose::INVOICE_REMINDER,
            Recipient::for(Channel::SMS, '+33612345678', 'Claire Rey'),
            SenderIdentity::kiveto('no-reply@kiveto.app'),
            SubjectReference::of('invoice', 'a'),
            IdempotencyKey::fromString('invoice:a'),
            RenderedContent::email('s', 'h', 't'),
            new \DateTimeImmutable(),
        );
    }

    public function testAMessageThatCannotBePreparedFailsWithoutContent(): void
    {
        $message = OutboundMessage::failedBeforeHandOver(
            OutboundMessageId::fromString('01960000-0000-7000-8000-000000000f01'),
            Messages::CLINIC,
            Messages::CLIENT,
            MessagePurpose::APPOINTMENT_CONFIRMATION,
            Channel::EMAIL,
            Recipient::for(Channel::EMAIL, 's.dubois@email.fr', 'Sophie Dubois'),
            SubjectReference::of('appointment', 'apt-1'),
            IdempotencyKey::fromString('appointment:apt-1:confirmation:1'),
            "Profil d'envoi de la clinique absent",
            new \DateTimeImmutable('2026-10-21 09:00:00'),
        );

        self::assertSame(DeliveryStatus::FAILED, $message->status());
        self::assertNull($message->sender());
        self::assertNull($message->content());
        self::assertSame('Confirmation de rendez-vous', $message->subjectLine());
        self::assertSame(1, $message->sequence());
        self::assertFalse($message->markFailed('again', new \DateTimeImmutable()));
    }

    public function testTheProviderAcceptingAMessageBillsIt(): void
    {
        $message = Messages::sms();
        self::assertNotEmpty($message->pullDomainEvents());

        self::assertTrue($message->markSent(' brevo-1 ', new \DateTimeImmutable('2026-10-21 09:31:00')));
        self::assertSame(DeliveryStatus::SENT, $message->status());
        self::assertSame('brevo-1', $message->externalMessageId());
        self::assertSame('2026-10-21 09:31:00', $message->sentAt()?->format('Y-m-d H:i:s'));
        self::assertSame(OutboundMessage::SOURCE_DISPATCH, $message->history()[1]->source);
        self::assertFalse($message->markSent('brevo-2', new \DateTimeImmutable()), 'A replayed outcome changes nothing.');
        self::assertSame('brevo-1', $message->externalMessageId());
        self::assertFalse($message->markFailed('late', new \DateTimeImmutable()));
        self::assertFalse($message->recordStatus(DeliveryStatus::SENT, new \DateTimeImmutable('2026-10-21 09:32:00'), OutboundMessage::SOURCE_PROVIDER), 'The provider « request » event adds nothing.');
        self::assertCount(1, $message->pullDomainEvents());
    }

    public function testASentMessageNeedsTheProviderId(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Messages::sms()->markSent(' ', new \DateTimeImmutable());
    }

    public function testAMessageTheProviderRefusesFails(): void
    {
        $message = Messages::email();

        self::assertTrue($message->markFailed('HTTP 400', new \DateTimeImmutable('2026-10-16 08:01:00')));
        self::assertSame(DeliveryStatus::FAILED, $message->status());
        self::assertSame('HTTP 400', $message->history()[1]->reason);
        self::assertNull($message->sentAt());
    }

    /**
     * AC 9: a late request / deferred event never replaces DELIVERED; the
     * same event posted twice is recorded once.
     */
    public function testAFinalStatusIsNeverOverwritten(): void
    {
        $message = $this->sent();

        self::assertTrue($message->recordStatus(DeliveryStatus::DELIVERED, new \DateTimeImmutable('2026-10-16 08:05:00'), OutboundMessage::SOURCE_PROVIDER));
        self::assertFalse($message->recordStatus(DeliveryStatus::SENT, new \DateTimeImmutable('2026-10-16 08:06:00'), OutboundMessage::SOURCE_PROVIDER));
        self::assertFalse($message->recordStatus(DeliveryStatus::DEFERRED, new \DateTimeImmutable('2026-10-16 08:06:00'), OutboundMessage::SOURCE_PROVIDER));
        self::assertFalse($message->recordStatus(DeliveryStatus::BOUNCED, new \DateTimeImmutable('2026-10-16 08:07:00'), OutboundMessage::SOURCE_PROVIDER));
        self::assertFalse($message->recordStatus(DeliveryStatus::DELIVERED, new \DateTimeImmutable('2026-10-16 08:05:00'), OutboundMessage::SOURCE_PROVIDER));

        self::assertSame(DeliveryStatus::DELIVERED, $message->status());
        self::assertCount(3, $message->history());
        self::assertSame(3, $message->sequence());
    }

    /**
     * AC 10: a soft bounce is DEFERRED, then DELIVERED.
     */
    public function testASoftBounceIsNeverFinal(): void
    {
        $message = $this->sent();

        self::assertTrue($message->recordStatus(DeliveryStatus::DEFERRED, new \DateTimeImmutable('2026-10-16 08:02:00'), OutboundMessage::SOURCE_PROVIDER, 'mailbox full'));
        self::assertFalse($message->recordStatus(DeliveryStatus::DEFERRED, new \DateTimeImmutable('2026-10-16 08:02:00'), OutboundMessage::SOURCE_PROVIDER), 'duplicate');
        self::assertFalse($message->recordStatus(DeliveryStatus::SENT, new \DateTimeImmutable('2026-10-16 08:01:30'), OutboundMessage::SOURCE_PROVIDER), 'older, same rank');
        self::assertTrue($message->recordStatus(DeliveryStatus::DELIVERED, new \DateTimeImmutable('2026-10-16 08:10:00'), OutboundMessage::SOURCE_PROVIDER));

        self::assertSame(
            ['QUEUED', 'SENT', 'DEFERRED', 'DELIVERED'],
            array_map(static fn ($change): string => $change->status->value, $message->history()),
        );
        self::assertSame('2026-10-16 08:10:00', $message->statusChangedAt()->format('Y-m-d H:i:s'));
    }

    public function testAHardBounceIsFinal(): void
    {
        $message = $this->sent();

        self::assertTrue($message->recordStatus(DeliveryStatus::BOUNCED, new \DateTimeImmutable('2026-10-16 08:02:00'), OutboundMessage::SOURCE_PROVIDER));
        self::assertTrue($message->status()->isFinal());
        self::assertSame('Non délivré', $message->status()->label());
    }

    public function testAQueuedMessageIgnoresALowerRankButTakesAFinalStatus(): void
    {
        $message = Messages::email();

        self::assertTrue($message->recordStatus(DeliveryStatus::REJECTED, new \DateTimeImmutable('2026-10-16 08:00:30'), OutboundMessage::SOURCE_PROVIDER));
        self::assertSame(DeliveryStatus::REJECTED, $message->status());
    }

    public function testAProviderNeverReportsQueuedOrCapped(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Messages::email()->recordStatus(DeliveryStatus::BLOCKED_CAP, new \DateTimeImmutable(), OutboundMessage::SOURCE_PROVIDER);
    }

    public function testAComplaintIsAFlagNotAStatus(): void
    {
        $message = $this->sent();
        $message->recordStatus(DeliveryStatus::DELIVERED, new \DateTimeImmutable('2026-10-16 08:05:00'), OutboundMessage::SOURCE_PROVIDER);

        self::assertTrue($message->recordComplaint(new \DateTimeImmutable('2026-10-17 10:00:00')));
        self::assertFalse($message->recordComplaint(new \DateTimeImmutable('2026-10-18 10:00:00')));
        self::assertSame('2026-10-17 10:00:00', $message->complainedAt()?->format('Y-m-d H:i:s'));
        self::assertSame(DeliveryStatus::DELIVERED, $message->status());
    }

    public function testThePurgeErasesTheContentButKeepsTheMetadata(): void
    {
        $email = $this->sent();

        self::assertTrue($email->purgeContent(new \DateTimeImmutable('2028-10-17 03:47:00')));
        self::assertFalse($email->purgeContent(new \DateTimeImmutable('2028-10-18 03:47:00')));
        self::assertNull($email->content());
        self::assertNull($email->recipient());
        self::assertSame('Envoi de facture', $email->subjectLine());
        self::assertSame('2028-10-17 03:47:00', $email->contentPurgedAt()?->format('Y-m-d H:i:s'));
        self::assertCount(1, $email->attachments(), 'The attachment references are kept to delete the owned files.');

        $sms = Messages::sms(segments: 3);
        $sms->purgeContent(new \DateTimeImmutable('2028-10-17 03:47:00'));
        self::assertSame(3, $sms->segments());
        self::assertSame('', $sms->content()?->smsText);
    }

    public function testItComesBackAsItWentIn(): void
    {
        $original = $this->sent();
        $copy     = OutboundMessage::reconstitute(
            $original->id(),
            $original->clinicId(),
            $original->clientId(),
            $original->purpose(),
            $original->channel(),
            $original->recipient(),
            $original->sender(),
            $original->subject(),
            $original->idempotencyKey(),
            $original->content(),
            $original->attachments(),
            $original->status(),
            $original->externalMessageId(),
            $original->history(),
            $original->complainedAt(),
            $original->createdAt(),
            $original->sentAt(),
            $original->statusChangedAt(),
            $original->contentPurgedAt(),
        );

        self::assertSame($original->history(), $copy->history());
        self::assertSame([], $copy->pullDomainEvents());
        self::assertSame(2, $copy->sequence());
    }

    /**
     * N6: the one-time link of an invitation or a password reset is not kept
     * once the message is handed over or failed; the subject stays.
     */
    public function testAOneTimeLinkIsRedactedOnceHandedOverOrFailed(): void
    {
        $sent = Messages::email(key: 'password-reset:t-1', clinicId: null, purpose: MessagePurpose::PASSWORD_RESET);
        $sent->markSent('b-1', new \DateTimeImmutable('2026-10-16 08:01:00'));
        $failed = Messages::email(key: 'invitation:t-2', clinicId: null, purpose: MessagePurpose::STAFF_INVITATION);
        $failed->markFailed('Refusé par le fournisseur (HTTP 400)', new \DateTimeImmutable('2026-10-16 08:01:00'));
        $sms = Messages::sms(purpose: MessagePurpose::PASSWORD_RESET);
        $sms->markSent('b-2', new \DateTimeImmutable('2026-10-21 09:31:00'));

        foreach ([$sent, $failed] as $message) {
            self::assertSame(['Votre facture FA-2026-0814 — Clinique Montmartre', '', ''], [$message->content()?->subject, $message->content()?->html, $message->content()?->text]);
            self::assertSame('t.lefebvre@orange.fr', $message->recipient()?->address);
        }
        self::assertSame(['', 1], [$sms->content()?->smsText, $sms->segments()]);

        $invoice = Messages::email();
        $invoice->markSent('b-3', new \DateTimeImmutable('2026-10-16 08:01:00'));
        self::assertSame('<p>Bonjour</p>', $invoice->content()?->html, 'other purposes keep their content');
    }

    /**
     * N10: a reset link lives 30 min, an invitation link 72 h, from the request.
     */
    public function testAOneTimeLinkExpiresWithItsToken(): void
    {
        $reset      = Messages::email(purpose: MessagePurpose::PASSWORD_RESET, at: new \DateTimeImmutable('2026-10-16 08:00:00'));
        $invitation = Messages::email(purpose: MessagePurpose::STAFF_INVITATION, at: new \DateTimeImmutable('2026-10-16 08:00:00'));

        self::assertFalse($reset->linkExpired(new \DateTimeImmutable('2026-10-16 08:29:59')));
        self::assertTrue($reset->linkExpired(new \DateTimeImmutable('2026-10-16 08:30:00')));
        self::assertFalse($invitation->linkExpired(new \DateTimeImmutable('2026-10-19 07:59:59')));
        self::assertTrue($invitation->linkExpired(new \DateTimeImmutable('2026-10-19 08:00:00')));
        self::assertFalse(Messages::email()->linkExpired(new \DateTimeImmutable('2030-01-01')), 'no link, never expired');
    }

    private function sent(): OutboundMessage
    {
        $message = Messages::email();
        $message->markSent('brevo-1', new \DateTimeImmutable('2026-10-16 08:01:00'));
        self::assertNotEmpty($message->pullDomainEvents());

        return $message;
    }
}
