<?php

declare(strict_types=1);

namespace App\System\Notification\Domain\Message;

use App\Shared\Domain\Aggregate\AggregateRoot;
use App\System\Notification\Domain\Delivery\DeliveryEvent;
use App\System\Notification\Domain\Event\OutboundMessageQueued;
use App\System\Notification\Domain\Event\OutboundMessageStatusChanged;

/**
 * One e-mail or one SMS to one recipient: the content frozen when it was
 * requested, its attachments, the provider message id and its status history.
 *
 * A message is created QUEUED (to be handed to the provider), BLOCKED_CAP (an
 * SMS held back by the overage cap) or FAILED (it could not be prepared, e.g.
 * no clinic messaging profile). Provider events then move it on with a strict
 * precedence: a final status is never overwritten, a lower rank never
 * replaces a higher one, an older event never replaces a newer one of the
 * same rank, and a provider duplicate (same status, same time) is recorded
 * once. Every change is recorded as a domain event whose sequence is the
 * length of the history, strictly increasing.
 *
 * The SMS segments are computed at render time and stored; they are billed
 * once the SMS is handed to the provider (`sentAt`).
 *
 * A message carrying a one-time link (invitation, password reset) keeps its
 * bodies only until it is handed over or fails: the link is a credential,
 * never stored longer than needed. Subject and metadata stay.
 */
final class OutboundMessage extends AggregateRoot
{
    public const string SOURCE_REQUEST  = 'request';
    public const string SOURCE_DISPATCH = 'dispatch';
    public const string SOURCE_PROVIDER = 'provider';
    public const string SOURCE_ERASURE  = 'erasure';

    /**
     * @param list<Attachment>   $attachments
     * @param list<StatusChange> $history
     */
    private function __construct(
        private readonly OutboundMessageId $id,
        private readonly ?string $clinicId,
        private readonly ?string $clientId,
        private readonly MessagePurpose $purpose,
        private readonly Channel $channel,
        private ?Recipient $recipient,
        private readonly ?SenderIdentity $sender,
        private readonly SubjectReference $subject,
        private readonly IdempotencyKey $idempotencyKey,
        private ?RenderedContent $content,
        private readonly array $attachments,
        private DeliveryStatus $status,
        private ?string $externalMessageId,
        private array $history,
        private ?\DateTimeImmutable $complainedAt,
        private readonly \DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $sentAt,
        private \DateTimeImmutable $statusChangedAt,
        private ?\DateTimeImmutable $contentPurgedAt,
        private readonly ?string $accountId = null,
        private ?\DateTimeImmutable $dispatchClaimedAt = null,
        private bool $erasurePending = false,
    ) {
    }

    /**
     * A message ready to be handed to the provider.
     *
     * @param list<Attachment> $attachments
     */
    public static function queue(
        OutboundMessageId $id,
        ?string $clinicId,
        ?string $clientId,
        MessagePurpose $purpose,
        Channel $channel,
        Recipient $recipient,
        SenderIdentity $sender,
        SubjectReference $subject,
        IdempotencyKey $idempotencyKey,
        RenderedContent $content,
        array $attachments,
        \DateTimeImmutable $now,
        ?string $accountId = null,
    ): self {
        $message = self::create($id, $clinicId, $clientId, $purpose, $channel, $recipient, $sender, $subject, $idempotencyKey, $content, $attachments, $now, $accountId);
        $message->recordDomainEvent(new OutboundMessageQueued($id->toString(), $clinicId, $channel->value, $now->format(\DateTimeInterface::ATOM)));
        $message->apply(new StatusChange(DeliveryStatus::QUEUED, $now, self::SOURCE_REQUEST));

        return $message;
    }

    /**
     * A non-critical SMS held back because the clinic reached its overage
     * cap: journaled, never sent, never billed.
     */
    public static function blockedByCap(
        OutboundMessageId $id,
        string $clinicId,
        ?string $clientId,
        MessagePurpose $purpose,
        Recipient $recipient,
        SenderIdentity $sender,
        SubjectReference $subject,
        IdempotencyKey $idempotencyKey,
        RenderedContent $content,
        \DateTimeImmutable $now,
    ): self {
        if (Criticality::NON_CRITICAL !== $purpose->criticality() || !$content->isSms()) {
            throw new \InvalidArgumentException('Only a non-critical SMS can be held back by the overage cap.');
        }

        $message = self::create($id, $clinicId, $clientId, $purpose, Channel::SMS, $recipient, $sender, $subject, $idempotencyKey, $content, [], $now, null);
        $message->apply(new StatusChange(DeliveryStatus::BLOCKED_CAP, $now, self::SOURCE_REQUEST, 'Plafond de dépassement SMS atteint'));

        return $message;
    }

    /**
     * A message that could not even be prepared: journaled FAILED, nothing
     * handed to a transport.
     */
    public static function failedBeforeHandOver(
        OutboundMessageId $id,
        ?string $clinicId,
        ?string $clientId,
        MessagePurpose $purpose,
        Channel $channel,
        Recipient $recipient,
        SubjectReference $subject,
        IdempotencyKey $idempotencyKey,
        string $reason,
        \DateTimeImmutable $now,
    ): self {
        $message = new self($id, $clinicId, $clientId, $purpose, $channel, $recipient, null, $subject, $idempotencyKey, null, [], DeliveryStatus::FAILED, null, [], null, $now, null, $now, null);
        $message->apply(new StatusChange(DeliveryStatus::FAILED, $now, self::SOURCE_REQUEST, $reason));

        return $message;
    }

    /**
     * @param list<Attachment>   $attachments
     * @param list<StatusChange> $history
     */
    public static function reconstitute(
        OutboundMessageId $id,
        ?string $clinicId,
        ?string $clientId,
        MessagePurpose $purpose,
        Channel $channel,
        ?Recipient $recipient,
        ?SenderIdentity $sender,
        SubjectReference $subject,
        IdempotencyKey $idempotencyKey,
        ?RenderedContent $content,
        array $attachments,
        DeliveryStatus $status,
        ?string $externalMessageId,
        array $history,
        ?\DateTimeImmutable $complainedAt,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $sentAt,
        \DateTimeImmutable $statusChangedAt,
        ?\DateTimeImmutable $contentPurgedAt,
        ?string $accountId = null,
        ?\DateTimeImmutable $dispatchClaimedAt = null,
        bool $erasurePending = false,
    ): self {
        return new self($id, $clinicId, $clientId, $purpose, $channel, $recipient, $sender, $subject, $idempotencyKey, $content, $attachments, $status, $externalMessageId, $history, $complainedAt, $createdAt, $sentAt, $statusChangedAt, $contentPurgedAt, $accountId, $dispatchClaimedAt, $erasurePending);
    }

    /**
     * The provider accepted the message: it is SENT and, for an SMS, its
     * segments are now billed. Only a QUEUED message can be handed over;
     * anything else is a replay and changes nothing — except a message
     * CANCELLED although the provider had it already: its provider id and
     * hand-over time are kept (traceability, billing), its status stays.
     */
    public function markSent(string $externalMessageId, \DateTimeImmutable $at): bool
    {
        $lateForCancelled = DeliveryStatus::CANCELLED === $this->status && null === $this->externalMessageId;
        if (DeliveryStatus::QUEUED !== $this->status && !$lateForCancelled) {
            return false;
        }

        $externalMessageId = mb_trim($externalMessageId);
        if ('' === $externalMessageId) {
            throw new \InvalidArgumentException('A sent message needs the provider message id.');
        }

        $this->externalMessageId = $externalMessageId;
        $this->sentAt            = $at;

        if ($lateForCancelled) {
            return true;
        }

        $this->redactOneTimeLink();
        $this->eraseIfPending($at);
        $this->apply(new StatusChange(DeliveryStatus::SENT, $at, self::SOURCE_DISPATCH));

        return true;
    }

    /**
     * The message could not be handed to the provider (refused request,
     * retries exhausted, delivery disabled). Only a QUEUED message fails this way.
     */
    public function markFailed(string $reason, \DateTimeImmutable $at): bool
    {
        if (DeliveryStatus::QUEUED !== $this->status) {
            return false;
        }

        $this->redactOneTimeLink();
        $this->eraseIfPending($at);
        $this->apply(new StatusChange(DeliveryStatus::FAILED, $at, self::SOURCE_DISPATCH, $reason));

        return true;
    }

    /**
     * The dispatch is about to hand the message over: from then on it may be
     * with the provider, so it can no longer be cancelled, only masked. Only
     * a QUEUED message is claimed; a retried dispatch claims it again.
     */
    public function claimForDispatch(\DateTimeImmutable $at): bool
    {
        if (DeliveryStatus::QUEUED !== $this->status) {
            return false;
        }

        $this->dispatchClaimedAt = $at;

        return true;
    }

    /**
     * The message is withdrawn before it was handed over (its Portal account
     * was deleted): CANCELLED, final, never sent. Only a QUEUED message not
     * claimed by its dispatch can be cancelled; the dispatch then skips it.
     */
    public function cancel(string $reason, \DateTimeImmutable $at): bool
    {
        if (DeliveryStatus::QUEUED !== $this->status || null !== $this->dispatchClaimedAt) {
            return false;
        }

        $this->apply(new StatusChange(DeliveryStatus::CANCELLED, $at, self::SOURCE_ERASURE, $reason));

        return true;
    }

    /**
     * Its Portal account was deleted (Kiveto's own messages to the owner,
     * MessagePurpose::isPortalAccountMessage()). Returns whether it changed.
     *
     * - QUEUED, not claimed, moot once the account is gone
     *   (MessagePurpose::isCancelledAtAccountDeletion()): masked and
     *   CANCELLED, never sent.
     * - QUEUED and still to be sent (the e-mail change notice, a security
     *   alert): it keeps its recipient for its dispatch and is masked as
     *   soon as its hand-over outcome (sent or failed) is recorded.
     * - Anything else (handed over, failed, or claimed by a dispatch that
     *   may already be talking to the provider): masked now.
     */
    public function eraseForDeletedAccount(string $cancelReason, \DateTimeImmutable $at): bool
    {
        if (DeliveryStatus::QUEUED === $this->status && !$this->purpose->isCancelledAtAccountDeletion()) {
            if ($this->erasurePending) {
                return false;
            }

            $this->erasurePending = true;

            return true;
        }

        $cancel = null === $this->dispatchClaimedAt && DeliveryStatus::QUEUED === $this->status;
        // Masked first: the cancellation's status event carries no recipient.
        $purged = $this->purgeContent($at);

        return ($cancel && $this->cancel($cancelReason, $at)) || $purged;
    }

    /**
     * Whether the one-time link of the message (invitation, password reset)
     * has expired by $now: sending it would only deliver a dead link.
     */
    public function linkExpired(\DateTimeImmutable $now): bool
    {
        $validity = $this->purpose->linkValidity();

        return null !== $validity && $this->createdAt->add($validity) <= $now;
    }

    /**
     * A status reported by the provider (webhook), applied with the status
     * precedence. Returns whether it changed the message.
     */
    public function recordStatus(DeliveryStatus $status, \DateTimeImmutable $occurredAt, string $source, ?string $reason = null): bool
    {
        if (\in_array($status, [DeliveryStatus::QUEUED, DeliveryStatus::BLOCKED_CAP, DeliveryStatus::CANCELLED], true)) {
            throw new \InvalidArgumentException(\sprintf('%s is not a status a provider reports.', $status->value));
        }

        if (!$this->accepts($status, $occurredAt)) {
            return false;
        }

        $this->apply(new StatusChange($status, $occurredAt, $source, $reason));

        return true;
    }

    /**
     * An event reported by the provider: a complaint sets the flag, a status
     * goes through the precedence. Returns whether it changed the message.
     */
    public function recordProviderEvent(DeliveryEvent $event): bool
    {
        if (null === $event->status) {
            return $this->recordComplaint($event->occurredAt);
        }

        return $this->recordStatus($event->status, $event->occurredAt, self::SOURCE_PROVIDER, $event->reason);
    }

    /** The recipient marked the message as spam: a flag, not a status. */
    public function recordComplaint(\DateTimeImmutable $at): bool
    {
        if (null !== $this->complainedAt) {
            return false;
        }

        $this->complainedAt = $at;

        return true;
    }

    /**
     * Retention: the bodies and the recipient are erased, the metadata
     * (purpose, channel, status, dates, segments, subject) stays.
     */
    public function purgeContent(\DateTimeImmutable $at): bool
    {
        if (null !== $this->contentPurgedAt) {
            return false;
        }

        $segments              = $this->segments();
        $this->content         = $this->content?->isSms() ? RenderedContent::sms('', $this->content->encoding ?? SmsEncoding::GSM_7, $segments) : null;
        $this->recipient       = null;
        $this->contentPurgedAt = $at;

        return true;
    }

    public function id(): OutboundMessageId
    {
        return $this->id;
    }

    public function clinicId(): ?string
    {
        return $this->clinicId;
    }

    public function clientId(): ?string
    {
        return $this->clientId;
    }

    public function purpose(): MessagePurpose
    {
        return $this->purpose;
    }

    public function channel(): Channel
    {
        return $this->channel;
    }

    public function criticality(): Criticality
    {
        return $this->purpose->criticality();
    }

    public function recipient(): ?Recipient
    {
        return $this->recipient;
    }

    public function sender(): ?SenderIdentity
    {
        return $this->sender;
    }

    public function subject(): SubjectReference
    {
        return $this->subject;
    }

    public function idempotencyKey(): IdempotencyKey
    {
        return $this->idempotencyKey;
    }

    public function content(): ?RenderedContent
    {
        return $this->content;
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return $this->attachments;
    }

    public function status(): DeliveryStatus
    {
        return $this->status;
    }

    public function externalMessageId(): ?string
    {
        return $this->externalMessageId;
    }

    /**
     * @return list<StatusChange>
     */
    public function history(): array
    {
        return $this->history;
    }

    public function complainedAt(): ?\DateTimeImmutable
    {
        return $this->complainedAt;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function sentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function statusChangedAt(): \DateTimeImmutable
    {
        return $this->statusChangedAt;
    }

    public function contentPurgedAt(): ?\DateTimeImmutable
    {
        return $this->contentPurgedAt;
    }

    /** The Portal account (user id) the message is about, for Kiveto's own messages to an owner. */
    public function accountId(): ?string
    {
        return $this->accountId;
    }

    public function dispatchClaimedAt(): ?\DateTimeImmutable
    {
        return $this->dispatchClaimedAt;
    }

    /** Masked as soon as its hand-over outcome is recorded (its account was deleted meanwhile). */
    public function erasurePending(): bool
    {
        return $this->erasurePending;
    }

    /** The SMS segments (0 for an e-mail), kept after the content purge. */
    public function segments(): int
    {
        return $this->content->segments ?? 0;
    }

    /** The e-mail subject, or the purpose label of an SMS or of a purged message. */
    public function subjectLine(): string
    {
        return $this->content->subject ?? $this->purpose->label();
    }

    /** The version of the message published with its changes. */
    public function sequence(): int
    {
        return \count($this->history);
    }

    /**
     * @param list<Attachment> $attachments
     */
    private static function create(
        OutboundMessageId $id,
        ?string $clinicId,
        ?string $clientId,
        MessagePurpose $purpose,
        Channel $channel,
        Recipient $recipient,
        SenderIdentity $sender,
        SubjectReference $subject,
        IdempotencyKey $idempotencyKey,
        RenderedContent $content,
        array $attachments,
        \DateTimeImmutable $now,
        ?string $accountId,
    ): self {
        if ((Channel::SMS === $channel) !== $content->isSms()) {
            throw new \InvalidArgumentException(\sprintf('The content does not fit the %s channel.', $channel->value));
        }

        if (Channel::SMS === $channel && [] !== $attachments) {
            throw new \InvalidArgumentException('An SMS carries no attachment.');
        }

        return new self($id, $clinicId, $clientId, $purpose, $channel, $recipient, $sender, $subject, $idempotencyKey, $content, $attachments, DeliveryStatus::QUEUED, null, [], null, $now, null, $now, null, $accountId);
    }

    private function eraseIfPending(\DateTimeImmutable $at): void
    {
        if ($this->erasurePending) {
            $this->purgeContent($at);
            $this->erasurePending = false;
        }
    }

    private function redactOneTimeLink(): void
    {
        if (null === $this->purpose->linkValidity() || null === $this->content) {
            return;
        }

        $this->content = $this->content->isSms()
            ? RenderedContent::sms('', $this->content->encoding ?? SmsEncoding::GSM_7, $this->content->segments)
            : RenderedContent::email($this->content->subject ?? '', '', '', $this->content->inlineLogoKey);
    }

    private function accepts(DeliveryStatus $status, \DateTimeImmutable $occurredAt): bool
    {
        if ($this->status->isFinal() || $status->rank() < $this->status->rank()) {
            return false;
        }

        // A provider « request » event on a message already handed over adds nothing.
        if (DeliveryStatus::SENT === $status && DeliveryStatus::SENT === $this->status) {
            return false;
        }

        foreach ($this->history as $change) {
            if ($change->sameAs($status, $occurredAt)) {
                return false;
            }
        }

        return !($status->rank() === $this->status->rank() && $occurredAt < $this->statusChangedAt);
    }

    private function apply(StatusChange $change): void
    {
        $this->status          = $change->status;
        $this->statusChangedAt = $change->occurredAt;
        $this->history[]       = $change;

        $this->recordDomainEvent(new OutboundMessageStatusChanged(
            messageId: $this->id->toString(),
            clinicId: $this->clinicId,
            clientId: $this->clientId,
            purpose: $this->purpose->value,
            channel: $this->channel->value,
            recipientLabel: $this->recipient?->label() ?? '',
            subjectLine: $this->subjectLine(),
            status: $change->status->value,
            statusLabel: $change->status->label(),
            reason: $change->reason,
            occurredAt: $change->occurredAt->format(\DateTimeInterface::ATOM),
            sequence: $this->sequence(),
        ));
    }
}
