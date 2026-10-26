<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Notification\Support;

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

/**
 * Builds outbound messages for the tests.
 */
final class Messages
{
    public const string CLINIC = '01960000-0000-7000-8000-00000000c11c';
    public const string CLIENT = '01960000-0000-7000-8000-00000000c1e7';

    public static function email(
        string $id = '01960000-0000-7000-8000-000000000e01',
        string $key = 'invoice:1:send:1',
        ?string $clinicId = self::CLINIC,
        MessagePurpose $purpose = MessagePurpose::INVOICE_SENT,
        ?\DateTimeImmutable $at = null,
    ): OutboundMessage {
        return OutboundMessage::queue(
            OutboundMessageId::fromString($id),
            $clinicId,
            self::CLIENT,
            $purpose,
            Channel::EMAIL,
            Recipient::for(Channel::EMAIL, 't.lefebvre@orange.fr', 'Thomas Lefebvre'),
            SenderIdentity::clinic('Clinique Montmartre', 'clinique-montmartre', 'mail.kiveto.fr', 'accueil@clinique-montmartre.fr', 'Montmartre'),
            SubjectReference::of('invoice', 'inv-1'),
            IdempotencyKey::fromString($key),
            RenderedContent::email('Votre facture FA-2026-0814 — Clinique Montmartre', '<p>Bonjour</p>', 'Bonjour', 'clinic/logo.png'),
            [Attachment::of('billing/sent/invoice/inv-1.pdf', 'FA-2026-0814.pdf', 'application/pdf', AttachmentOwner::REQUESTER)],
            $at ?? new \DateTimeImmutable('2026-10-16 08:00:00'),
        );
    }

    public static function sms(
        string $id = '01960000-0000-7000-8000-000000000501',
        string $key = 'appointment:1:reminder',
        string $clinicId = self::CLINIC,
        int $segments = 1,
        MessagePurpose $purpose = MessagePurpose::APPOINTMENT_REMINDER,
        ?\DateTimeImmutable $at = null,
    ): OutboundMessage {
        return OutboundMessage::queue(
            OutboundMessageId::fromString($id),
            $clinicId,
            self::CLIENT,
            $purpose,
            Channel::SMS,
            Recipient::for(Channel::SMS, '+33612345678', 'Sophie Dubois'),
            SenderIdentity::clinic('Clinique Montmartre', 'clinique-montmartre', 'mail.kiveto.fr', null, 'Montmartre'),
            SubjectReference::of('appointment', 'apt-1'),
            IdempotencyKey::fromString($key),
            RenderedContent::sms('Clinique Montmartre : rappel du RDV de Milo demain a 9h30.', SmsEncoding::GSM_7, $segments),
            [],
            $at ?? new \DateTimeImmutable('2026-10-21 09:30:00'),
        );
    }
}
