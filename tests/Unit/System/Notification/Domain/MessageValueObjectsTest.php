<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Notification\Domain;

use App\System\Notification\Domain\Event\OutboundMessageQueued;
use App\System\Notification\Domain\Event\OutboundMessageStatusChanged;
use App\System\Notification\Domain\Event\OutboundMessageStatusChangedIntegrationEvent;
use App\System\Notification\Domain\Exception\OutboundMessageNotFoundException;
use App\System\Notification\Domain\Message\Attachment;
use App\System\Notification\Domain\Message\AttachmentOwner;
use App\System\Notification\Domain\Message\Channel;
use App\System\Notification\Domain\Message\Criticality;
use App\System\Notification\Domain\Message\DeliveryStatus;
use App\System\Notification\Domain\Message\IdempotencyKey;
use App\System\Notification\Domain\Message\MessagePurpose;
use App\System\Notification\Domain\Message\Recipient;
use App\System\Notification\Domain\Message\RenderedContent;
use App\System\Notification\Domain\Message\SenderIdentity;
use App\System\Notification\Domain\Message\SmsEncoding;
use App\System\Notification\Domain\Message\StatusChange;
use App\System\Notification\Domain\Message\SubjectReference;
use PHPUnit\Framework\TestCase;

final class MessageValueObjectsTest extends TestCase
{
    public function testEveryPurposeHasAFixedCriticalityLabelAndSender(): void
    {
        $nonCritical = array_values(array_filter(MessagePurpose::cases(), static fn (MessagePurpose $p): bool => Criticality::NON_CRITICAL === $p->criticality()));
        $kiveto      = array_values(array_filter(MessagePurpose::cases(), static fn (MessagePurpose $p): bool => $p->isSentByKiveto()));

        self::assertSame([MessagePurpose::APPOINTMENT_REMINDER, MessagePurpose::INVOICE_REMINDER], $nonCritical);
        self::assertSame([MessagePurpose::STAFF_INVITATION, MessagePurpose::PASSWORD_RESET, MessagePurpose::TEST], $kiveto);

        $labels = array_map(static fn (MessagePurpose $p): string => $p->label(), MessagePurpose::cases());
        self::assertSame($labels, array_unique($labels));
        self::assertSame("Relance d'impayé", MessagePurpose::INVOICE_REMINDER->label());
    }

    public function testStatusesAreRankedAndLabelled(): void
    {
        $ranks = [];
        foreach (DeliveryStatus::cases() as $status) {
            $ranks[$status->value] = [$status->rank(), $status->label(), $status->isFinal()];
        }

        self::assertSame([
            'QUEUED'      => [0, 'En cours', false],
            'SENT'        => [1, 'Envoyé', false],
            'DEFERRED'    => [1, 'Différé', false],
            'DELIVERED'   => [2, 'Délivré', true],
            'BOUNCED'     => [2, 'Non délivré', true],
            'REJECTED'    => [2, 'Rejeté', true],
            'FAILED'      => [2, 'Échec', true],
            'BLOCKED_CAP' => [2, 'Bloqué (plafond)', true],
        ], $ranks);
    }

    public function testEncodingsSetTheSegmentLengths(): void
    {
        self::assertSame([160, 153], [SmsEncoding::GSM_7->singleSegmentLength(), SmsEncoding::GSM_7->multipartSegmentLength()]);
        self::assertSame([70, 67], [SmsEncoding::UCS_2->singleSegmentLength(), SmsEncoding::UCS_2->multipartSegmentLength()]);
    }

    public function testRecipientsAreValidatedPerChannel(): void
    {
        self::assertSame('s.dubois@email.fr', Recipient::for(Channel::EMAIL, ' s.dubois@email.fr ', ' Sophie Dubois ')->address);
        self::assertSame('+33612345678', Recipient::for(Channel::SMS, '+33612345678', '')->label());
        self::assertSame('x', Recipient::reconstitute('x', '')->address);

        foreach ([[Channel::EMAIL, 'not-an-address'], [Channel::SMS, '0612345678'], [Channel::SMS, 's.dubois@email.fr']] as [$channel, $address]) {
            try {
                Recipient::for($channel, $address, 'X');
                self::fail($address . ' should be refused.');
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testSenders(): void
    {
        $kiveto = SenderIdentity::kiveto('no-reply@kiveto.fr');
        self::assertSame(['Kiveto', 'no-reply@kiveto.fr', null, 'Kiveto'], [$kiveto->name, $kiveto->emailAddress, $kiveto->replyTo, $kiveto->smsSender]);

        $clinic = SenderIdentity::clinic('Clinique Montmartre', 'clinique-montmartre', 'mail.kiveto.fr', ' accueil@montmartre.fr ', ' Lilas Vet ');
        self::assertSame(['clinique-montmartre@mail.kiveto.fr', 'accueil@montmartre.fr', 'Lilas Vet'], [$clinic->emailAddress, $clinic->replyTo, $clinic->smsSender]);

        $copy = SenderIdentity::reconstitute('A', 'a@b.fr', null, 'A');
        self::assertSame('a@b.fr', $copy->emailAddress);
    }

    public function testRenderedContent(): void
    {
        $email = RenderedContent::email('S', '<p>H</p>', 'T');
        self::assertFalse($email->isSms());
        self::assertNull($email->inlineLogoKey);
        self::assertSame('logo', RenderedContent::INLINE_LOGO_CID);

        $sms = RenderedContent::sms('Bonjour', SmsEncoding::UCS_2, 2);
        self::assertTrue($sms->isSms());
        self::assertSame(2, $sms->segments);

        $this->expectException(\InvalidArgumentException::class);
        RenderedContent::sms('x', SmsEncoding::GSM_7, 0);
    }

    public function testAttachmentsNeedAKeyANameAndAType(): void
    {
        $attachment = Attachment::of('k', 'f.pdf', 'application/pdf', AttachmentOwner::NOTIFICATION);
        self::assertSame(AttachmentOwner::NOTIFICATION, $attachment->owner);

        $this->expectException(\InvalidArgumentException::class);
        Attachment::of('k', ' ', 'application/pdf', AttachmentOwner::REQUESTER);
    }

    public function testSubjectReferencesAreBounded(): void
    {
        self::assertSame('apt-1', SubjectReference::of(' appointment ', ' apt-1 ')->id);

        $this->expectException(\InvalidArgumentException::class);
        SubjectReference::of('appointment', str_repeat('x', 65));
    }

    public function testIdempotencyKeys(): void
    {
        $key = IdempotencyKey::fromString(' appointment:1:reminder ');

        self::assertSame('appointment:1:reminder', $key->toString());
        self::assertSame('appointment:1:reminder:fallback', $key->fallback()->toString());

        $this->expectException(\InvalidArgumentException::class);
        IdempotencyKey::fromString(str_repeat('k', 191));
    }

    /**
     * N9: a requested key leaves room for « :fallback », so that the
     * fallback key of the longest one still fits the column.
     */
    public function testARequestedKeyLeavesRoomForItsFallback(): void
    {
        $longest = IdempotencyKey::requested(str_repeat('k', 181));

        self::assertSame(190, mb_strlen($longest->fallback()->toString()));

        $this->expectException(\InvalidArgumentException::class);
        IdempotencyKey::requested(str_repeat('k', 182));
    }

    public function testAnEmptyIdempotencyKeyIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        IdempotencyKey::fromString('');
    }

    public function testAStatusChangeRecognisesAProviderDuplicate(): void
    {
        $change = new StatusChange(DeliveryStatus::DELIVERED, new \DateTimeImmutable('2026-10-16 08:05:00'), 'provider');

        self::assertTrue($change->sameAs(DeliveryStatus::DELIVERED, new \DateTimeImmutable('2026-10-16T10:05:00+02:00')));
        self::assertFalse($change->sameAs(DeliveryStatus::BOUNCED, new \DateTimeImmutable('2026-10-16 08:05:00')));
        self::assertNull($change->reason);
    }

    public function testEventsCarryTheirPayload(): void
    {
        $queued = new OutboundMessageQueued('m1', 'c1', 'SMS', '2026-10-16T08:00:00+00:00');
        self::assertSame('notification.outbound-message.queued.v1', $queued->name());
        self::assertSame('m1', $queued->aggregateId());
        self::assertSame(['messageId' => 'm1', 'clinicId' => 'c1', 'channel' => 'SMS', 'occurredAt' => '2026-10-16T08:00:00+00:00'], $queued->payload());

        $args = ['c1', 'cl1', 'INVOICE_SENT', 'EMAIL', 'Thomas Lefebvre', 'Votre facture', 'SENT', 'Envoyé', null, '2026-10-16T08:01:00+00:00', 2];

        $changed = new OutboundMessageStatusChanged('m1', ...$args);
        self::assertSame('notification.outbound-message-status.changed.v1', $changed->name());
        self::assertSame('m1', $changed->aggregateId());
        self::assertSame('m1', $changed->payload()['messageId']);
        self::assertSame(2, $changed->payload()['sequence']);

        $integration = new OutboundMessageStatusChangedIntegrationEvent('m1', ...$args);
        self::assertSame('m1', $integration->aggregateId());
        self::assertSame([
            'id'             => 'm1',
            'clinicId'       => 'c1',
            'clientId'       => 'cl1',
            'purpose'        => 'INVOICE_SENT',
            'channel'        => 'EMAIL',
            'recipientLabel' => 'Thomas Lefebvre',
            'subjectLine'    => 'Votre facture',
            'status'         => 'SENT',
            'statusLabel'    => 'Envoyé',
            'reason'         => null,
            'occurredAt'     => '2026-10-16T08:01:00+00:00',
            'sequence'       => 2,
        ], $integration->payload());
    }

    public function testAMissingMessageIsNamed(): void
    {
        self::assertSame('Outbound message "m1" not found.', new OutboundMessageNotFoundException('m1')->getMessage());
    }
}
