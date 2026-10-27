<?php

declare(strict_types=1);

namespace App\System\Notification\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * One outbound e-mail or SMS. Dates are stored in UTC.
 */
#[ORM\Entity]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_notif_idempotency', columns: ['idempotency_key'])]
#[ORM\Index(name: 'idx_notif_external', columns: ['channel', 'external_message_id'])]
#[ORM\Index(name: 'idx_notif_clinic_sent', columns: ['clinic_id', 'channel', 'sent_at'])]
#[ORM\Index(name: 'idx_notif_clinic_status', columns: ['clinic_id', 'channel', 'status'])]
#[ORM\Index(name: 'idx_notif_purge', columns: ['content_purged_at', 'created_at'])]
class OutboundMessageEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'clinic_id', type: UuidType::NAME, nullable: true)]
    private ?Uuid $clinicId = null;

    #[ORM\Column(name: 'client_id', type: UuidType::NAME, nullable: true)]
    private ?Uuid $clientId = null;

    #[ORM\Column(type: 'string', length: 32)]
    private string $purpose;

    #[ORM\Column(type: 'string', length: 8)]
    private string $channel;

    #[ORM\Column(type: 'string', length: 16)]
    private string $criticality;

    #[ORM\Column(name: 'recipient_address', type: 'string', length: 320, nullable: true)]
    private ?string $recipientAddress = null;

    #[ORM\Column(name: 'recipient_name', type: 'string', length: 255, nullable: true)]
    private ?string $recipientName = null;

    /** @var array{name: string, email: string, replyTo: ?string, sms: string}|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $sender = null;

    #[ORM\Column(name: 'subject_type', type: 'string', length: 40)]
    private string $subjectType;

    #[ORM\Column(name: 'subject_id', type: 'string', length: 64)]
    private string $subjectId;

    #[ORM\Column(name: 'idempotency_key', type: 'string', length: 190)]
    private string $idempotencyKey;

    /** @var array{subject: ?string, html: ?string, text: ?string, inlineLogoKey: ?string, smsText: ?string, encoding: ?string, segments: int}|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $content = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $segments = 0;

    /** @var list<array{storageKey: string, filename: string, mime: string, owner: string}> */
    #[ORM\Column(type: 'json')]
    private array $attachments = [];

    #[ORM\Column(type: 'string', length: 16)]
    private string $status;

    #[ORM\Column(name: 'external_message_id', type: 'string', length: 128, nullable: true)]
    private ?string $externalMessageId = null;

    /** @var list<array{status: string, occurredAt: string, source: string, reason: ?string}> */
    #[ORM\Column(name: 'status_history', type: 'json')]
    private array $statusHistory = [];

    #[ORM\Column(name: 'complained_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $complainedAt = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'sent_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(name: 'status_changed_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $statusChangedAt;

    #[ORM\Column(name: 'content_purged_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $contentPurgedAt = null;

    /** Optimistic lock: a concurrent change fails the flush and the message is retried. */
    #[ORM\Version]
    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $version = 1;

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function setId(Uuid $id): void
    {
        $this->id = $id;
    }

    public function getClinicId(): ?Uuid
    {
        return $this->clinicId;
    }

    public function setClinicId(?Uuid $clinicId): void
    {
        $this->clinicId = $clinicId;
    }

    public function getClientId(): ?Uuid
    {
        return $this->clientId;
    }

    public function setClientId(?Uuid $clientId): void
    {
        $this->clientId = $clientId;
    }

    public function getPurpose(): string
    {
        return $this->purpose;
    }

    public function setPurpose(string $purpose): void
    {
        $this->purpose = $purpose;
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function setChannel(string $channel): void
    {
        $this->channel = $channel;
    }

    public function getCriticality(): string
    {
        return $this->criticality;
    }

    public function setCriticality(string $criticality): void
    {
        $this->criticality = $criticality;
    }

    public function getRecipientAddress(): ?string
    {
        return $this->recipientAddress;
    }

    public function setRecipientAddress(?string $recipientAddress): void
    {
        $this->recipientAddress = $recipientAddress;
    }

    public function getRecipientName(): ?string
    {
        return $this->recipientName;
    }

    public function setRecipientName(?string $recipientName): void
    {
        $this->recipientName = $recipientName;
    }

    /**
     * @return array{name: string, email: string, replyTo: ?string, sms: string}|null
     */
    public function getSender(): ?array
    {
        return $this->sender;
    }

    /**
     * @param array{name: string, email: string, replyTo: ?string, sms: string}|null $sender
     */
    public function setSender(?array $sender): void
    {
        $this->sender = $sender;
    }

    public function getSubjectType(): string
    {
        return $this->subjectType;
    }

    public function setSubjectType(string $subjectType): void
    {
        $this->subjectType = $subjectType;
    }

    public function getSubjectId(): string
    {
        return $this->subjectId;
    }

    public function setSubjectId(string $subjectId): void
    {
        $this->subjectId = $subjectId;
    }

    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function setIdempotencyKey(string $idempotencyKey): void
    {
        $this->idempotencyKey = $idempotencyKey;
    }

    /**
     * @return array{subject: ?string, html: ?string, text: ?string, inlineLogoKey: ?string, smsText: ?string, encoding: ?string, segments: int}|null
     */
    public function getContent(): ?array
    {
        return $this->content;
    }

    /**
     * @param array{subject: ?string, html: ?string, text: ?string, inlineLogoKey: ?string, smsText: ?string, encoding: ?string, segments: int}|null $content
     */
    public function setContent(?array $content): void
    {
        $this->content = $content;
    }

    public function getSegments(): int
    {
        return $this->segments;
    }

    public function setSegments(int $segments): void
    {
        $this->segments = $segments;
    }

    /**
     * @return list<array{storageKey: string, filename: string, mime: string, owner: string}>
     */
    public function getAttachments(): array
    {
        return $this->attachments;
    }

    /**
     * @param list<array{storageKey: string, filename: string, mime: string, owner: string}> $attachments
     */
    public function setAttachments(array $attachments): void
    {
        $this->attachments = $attachments;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getExternalMessageId(): ?string
    {
        return $this->externalMessageId;
    }

    public function setExternalMessageId(?string $externalMessageId): void
    {
        $this->externalMessageId = $externalMessageId;
    }

    /**
     * @return list<array{status: string, occurredAt: string, source: string, reason: ?string}>
     */
    public function getStatusHistory(): array
    {
        return $this->statusHistory;
    }

    /**
     * @param list<array{status: string, occurredAt: string, source: string, reason: ?string}> $statusHistory
     */
    public function setStatusHistory(array $statusHistory): void
    {
        $this->statusHistory = $statusHistory;
    }

    public function getComplainedAt(): ?\DateTimeImmutable
    {
        return $this->complainedAt;
    }

    public function setComplainedAt(?\DateTimeImmutable $complainedAt): void
    {
        $this->complainedAt = $complainedAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function setSentAt(?\DateTimeImmutable $sentAt): void
    {
        $this->sentAt = $sentAt;
    }

    public function getStatusChangedAt(): \DateTimeImmutable
    {
        return $this->statusChangedAt;
    }

    public function setStatusChangedAt(\DateTimeImmutable $statusChangedAt): void
    {
        $this->statusChangedAt = $statusChangedAt;
    }

    public function getContentPurgedAt(): ?\DateTimeImmutable
    {
        return $this->contentPurgedAt;
    }

    public function setContentPurgedAt(?\DateTimeImmutable $contentPurgedAt): void
    {
        $this->contentPurgedAt = $contentPurgedAt;
    }

    public function getVersion(): int
    {
        return $this->version;
    }
}
