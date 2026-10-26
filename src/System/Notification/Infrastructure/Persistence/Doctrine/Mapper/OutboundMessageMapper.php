<?php

declare(strict_types=1);

namespace App\System\Notification\Infrastructure\Persistence\Doctrine\Mapper;

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
use App\System\Notification\Domain\Message\StatusChange;
use App\System\Notification\Domain\Message\SubjectReference;
use App\System\Notification\Infrastructure\Persistence\Doctrine\Entity\OutboundMessageEntity;
use Symfony\Component\Uid\Uuid;

/**
 * Dates are written in UTC and read back as UTC.
 */
final readonly class OutboundMessageMapper
{
    public function toDomain(OutboundMessageEntity $entity): OutboundMessage
    {
        $address = $entity->getRecipientAddress();
        $sender  = $entity->getSender();
        $content = $entity->getContent();

        return OutboundMessage::reconstitute(
            id: OutboundMessageId::fromString($entity->getId()->toRfc4122()),
            clinicId: $entity->getClinicId()?->toRfc4122(),
            clientId: $entity->getClientId()?->toRfc4122(),
            purpose: MessagePurpose::from($entity->getPurpose()),
            channel: Channel::from($entity->getChannel()),
            recipient: null !== $address ? Recipient::reconstitute($address, $entity->getRecipientName() ?? '') : null,
            sender: null !== $sender ? SenderIdentity::reconstitute($sender['name'], $sender['email'], $sender['replyTo'], $sender['sms']) : null,
            subject: SubjectReference::of($entity->getSubjectType(), $entity->getSubjectId()),
            idempotencyKey: IdempotencyKey::fromString($entity->getIdempotencyKey()),
            content: null !== $content ? self::content($content) : null,
            attachments: array_map(
                static fn (array $a): Attachment => Attachment::of($a['storageKey'], $a['filename'], $a['mime'], AttachmentOwner::from($a['owner'])),
                $entity->getAttachments(),
            ),
            status: DeliveryStatus::from($entity->getStatus()),
            externalMessageId: $entity->getExternalMessageId(),
            history: array_map(
                static fn (array $h): StatusChange => new StatusChange(DeliveryStatus::from($h['status']), new \DateTimeImmutable($h['occurredAt']), $h['source'], $h['reason']),
                $entity->getStatusHistory(),
            ),
            complainedAt: $entity->getComplainedAt(),
            createdAt: $entity->getCreatedAt(),
            sentAt: $entity->getSentAt(),
            statusChangedAt: $entity->getStatusChangedAt(),
            contentPurgedAt: $entity->getContentPurgedAt(),
        );
    }

    public function toEntity(OutboundMessage $message, ?OutboundMessageEntity $entity = null): OutboundMessageEntity
    {
        $entity ??= new OutboundMessageEntity();
        $sender   = $message->sender();
        $content  = $message->content();
        $clinicId = $message->clinicId();
        $clientId = $message->clientId();

        $entity->setId(Uuid::fromString($message->id()->toString()));
        $entity->setClinicId(null !== $clinicId ? Uuid::fromString($clinicId) : null);
        $entity->setClientId(null !== $clientId ? Uuid::fromString($clientId) : null);
        $entity->setPurpose($message->purpose()->value);
        $entity->setChannel($message->channel()->value);
        $entity->setCriticality($message->criticality()->value);
        $entity->setRecipientAddress($message->recipient()?->address);
        $entity->setRecipientName($message->recipient()?->name);
        $entity->setSender(null !== $sender ? ['name' => $sender->name, 'email' => $sender->emailAddress, 'replyTo' => $sender->replyTo, 'sms' => $sender->smsSender] : null);
        $entity->setSubjectType($message->subject()->type);
        $entity->setSubjectId($message->subject()->id);
        $entity->setIdempotencyKey($message->idempotencyKey()->toString());
        $entity->setContent(null !== $content ? [
            'subject'       => $content->subject,
            'html'          => $content->html,
            'text'          => $content->text,
            'inlineLogoKey' => $content->inlineLogoKey,
            'smsText'       => $content->smsText,
            'encoding'      => $content->encoding?->value,
            'segments'      => $content->segments,
        ] : null);
        $entity->setSegments($message->segments());
        $entity->setAttachments(array_map(
            static fn (Attachment $a): array => ['storageKey' => $a->storageKey, 'filename' => $a->filename, 'mime' => $a->mime, 'owner' => $a->owner->value],
            $message->attachments(),
        ));
        $entity->setStatus($message->status()->value);
        $entity->setExternalMessageId($message->externalMessageId());
        $entity->setStatusHistory(array_map(
            static fn (StatusChange $h): array => ['status' => $h->status->value, 'occurredAt' => self::utc($h->occurredAt)->format(\DateTimeInterface::ATOM), 'source' => $h->source, 'reason' => $h->reason],
            $message->history(),
        ));
        $entity->setComplainedAt(self::utcOrNull($message->complainedAt()));
        $entity->setCreatedAt(self::utc($message->createdAt()));
        $entity->setSentAt(self::utcOrNull($message->sentAt()));
        $entity->setStatusChangedAt(self::utc($message->statusChangedAt()));
        $entity->setContentPurgedAt(self::utcOrNull($message->contentPurgedAt()));
        // The version column is Doctrine's optimistic lock, never copied.

        return $entity;
    }

    public static function utc(\DateTimeImmutable $at): \DateTimeImmutable
    {
        return $at->setTimezone(new \DateTimeZone('UTC'));
    }

    private static function utcOrNull(?\DateTimeImmutable $at): ?\DateTimeImmutable
    {
        return null !== $at ? self::utc($at) : null;
    }

    /**
     * @param array{subject: ?string, html: ?string, text: ?string, inlineLogoKey: ?string, smsText: ?string, encoding: ?string, segments: int} $content
     */
    private static function content(array $content): RenderedContent
    {
        if (null !== $content['smsText']) {
            return RenderedContent::sms($content['smsText'], SmsEncoding::from($content['encoding'] ?? SmsEncoding::GSM_7->value), $content['segments']);
        }

        return RenderedContent::email($content['subject'] ?? '', $content['html'] ?? '', $content['text'] ?? '', $content['inlineLogoKey']);
    }
}
