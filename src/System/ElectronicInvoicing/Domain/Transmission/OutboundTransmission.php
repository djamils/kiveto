<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Domain\Transmission;

use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Event\DomainEventInterface;
use App\Shared\Domain\ValueObject\Siren;
use App\System\ElectronicInvoicing\Domain\Connection\PlatformConnectionId;
use App\System\ElectronicInvoicing\Domain\Event\LifecycleStatusRecorded;
use App\System\ElectronicInvoicing\Domain\Event\TransmissionAwaitingInvoice;
use App\System\ElectronicInvoicing\Domain\Event\TransmissionBlocked;
use App\System\ElectronicInvoicing\Domain\Event\TransmissionNotRequired;
use App\System\ElectronicInvoicing\Domain\Event\TransmissionPrepared;
use App\System\ElectronicInvoicing\Domain\Event\TransmissionQueued;
use App\System\ElectronicInvoicing\Domain\Event\TransmissionSubmitted;
use App\System\ElectronicInvoicing\Domain\Event\TransmissionSubmitting;
use App\System\ElectronicInvoicing\Domain\Exception\InvalidTransmissionTransitionException;

/**
 * One document on its way to the organisation's approved platform, and the
 * lifecycle statuses the platform reported for it.
 *
 * The neutral payload is kept as requested; the file is rendered once, when
 * the transmission starts submitting, and from then on the same bytes (same
 * sha256) are sent on every retry, so a platform that already has them
 * answers with the same flow id.
 *
 * What the platform reports is never lost: a receipt, a flow id or a status
 * is recorded whatever Kiveto thought of the transmission — waiting, blocked
 * or internal. It proves the platform has the file, so the transmission moves
 * to submitted; only a second deposit of the same document stays an alarm.
 * Duplicates are ignored, never an error that would make a message loop.
 *
 * A credit note waits for its invoice (AWAITING_INVOICE, no submission
 * attempt counted) until that invoice is submitted. It is only kept out of
 * the platform — an internal credit note — when its invoice was refused (210)
 * or rejected (213): that invoice no longer exists for the tax authority.
 */
final class OutboundTransmission extends AggregateRoot
{
    public const int MAX_SUBMISSION_ATTEMPTS = 10;

    /**
     * @param array<string, mixed> $payload
     * @param list<LifecycleEvent> $lifecycle
     */
    private function __construct(
        private readonly OutboundTransmissionId $id,
        private readonly string $clinicId,
        private readonly TransmissionKind $kind,
        private readonly TrackingId $trackingId,
        private readonly string $documentNumber,
        private readonly \DateTimeImmutable $documentDate,
        private readonly Siren $sellerSiren,
        private readonly ?Siren $buyerSiren,
        private readonly ?string $precedingDocumentNumber,
        private readonly array $payload,
        private ?PlatformConnectionId $connectionId,
        private TransmissionState $state,
        private ?BlockReason $blockReason,
        private ?string $detail,
        private ?DocumentReference $document,
        private ?ElectronicAddress $addressUsed,
        private ?FlowId $flowId,
        private ?\DateTimeImmutable $submissionRequestedAt,
        private int $submissionAttempts,
        private ?\DateTimeImmutable $submittedAt,
        private array $lifecycle,
        private ?LifecycleStatusCode $internalBecause,
        private int $sequence,
        private readonly \DateTimeImmutable $preparedAt,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function prepare(
        OutboundTransmissionId $id,
        string $clinicId,
        TransmissionKind $kind,
        TrackingId $trackingId,
        string $documentNumber,
        \DateTimeImmutable $documentDate,
        Siren $sellerSiren,
        ?Siren $buyerSiren,
        ?string $precedingDocumentNumber,
        array $payload,
        \DateTimeImmutable $now,
    ): self {
        $transmission = new self(
            $id,
            $clinicId,
            $kind,
            $trackingId,
            $documentNumber,
            $documentDate,
            $sellerSiren,
            $buyerSiren,
            $precedingDocumentNumber,
            $payload,
            null,
            TransmissionState::PREPARED,
            null,
            null,
            null,
            null,
            null,
            null,
            0,
            null,
            [],
            null,
            0,
            $now,
        );

        $transmission->changed(TransmissionPrepared::class, $now);

        return $transmission;
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<LifecycleEvent> $lifecycle
     */
    public static function reconstitute(
        OutboundTransmissionId $id,
        string $clinicId,
        TransmissionKind $kind,
        TrackingId $trackingId,
        string $documentNumber,
        \DateTimeImmutable $documentDate,
        Siren $sellerSiren,
        ?Siren $buyerSiren,
        ?string $precedingDocumentNumber,
        array $payload,
        ?PlatformConnectionId $connectionId,
        TransmissionState $state,
        ?BlockReason $blockReason,
        ?string $detail,
        ?DocumentReference $document,
        ?ElectronicAddress $addressUsed,
        ?FlowId $flowId,
        ?\DateTimeImmutable $submissionRequestedAt,
        int $submissionAttempts,
        ?\DateTimeImmutable $submittedAt,
        array $lifecycle,
        ?LifecycleStatusCode $internalBecause,
        int $sequence,
        \DateTimeImmutable $preparedAt,
    ): self {
        return new self(
            $id,
            $clinicId,
            $kind,
            $trackingId,
            $documentNumber,
            $documentDate,
            $sellerSiren,
            $buyerSiren,
            $precedingDocumentNumber,
            $payload,
            $connectionId,
            $state,
            $blockReason,
            $detail,
            $document,
            $addressUsed,
            $flowId,
            $submissionRequestedAt,
            $submissionAttempts,
            $submittedAt,
            $lifecycle,
            $internalBecause,
            $sequence,
            $preparedAt,
        );
    }

    /**
     * Queues the transmission on its organisation's verified connection and
     * counts the attempt; past the cap it is blocked instead.
     *
     * @return bool whether a submission should be sent
     */
    public function requestSubmission(PlatformConnectionId $connectionId, \DateTimeImmutable $now): bool
    {
        if (!\in_array($this->state, [TransmissionState::PREPARED, TransmissionState::SUBMITTING], true)) {
            return false;
        }

        ++$this->submissionAttempts;
        $this->submissionRequestedAt = $now;

        return $this->queued($connectionId, $now);
    }

    /**
     * The same, once the request was already marked row by row in storage
     * (concurrent release or catch-up): the attempt is counted there.
     *
     * @return bool whether a submission should be sent
     */
    public function acknowledgeSubmissionRequested(PlatformConnectionId $connectionId, \DateTimeImmutable $now): bool
    {
        if (!\in_array($this->state, [TransmissionState::PREPARED, TransmissionState::SUBMITTING], true)) {
            return false;
        }

        return $this->queued($connectionId, $now);
    }

    /**
     * The file was rendered and stored: from now on these exact bytes are sent.
     */
    public function markSubmitting(DocumentReference $document, ElectronicAddress $addressUsed, \DateTimeImmutable $now): void
    {
        if (TransmissionState::SUBMITTING === $this->state) {
            return;
        }

        if (TransmissionState::PREPARED !== $this->state) {
            throw new InvalidTransmissionTransitionException($this->id->toString(), $this->state, TransmissionState::SUBMITTING);
        }

        $this->state       = TransmissionState::SUBMITTING;
        $this->document    = $document;
        $this->addressUsed = $addressUsed;

        $this->changed(TransmissionSubmitting::class, $now);
    }

    /**
     * The platform received the file under $flowId — recorded whatever the
     * local state. The same flow id again is a no-op; another flow id for a
     * document already submitted is a double deposit: kept in the detail, and
     * the transmission blocked.
     */
    public function markSubmitted(FlowId $flowId, \DateTimeImmutable $at): void
    {
        if (null !== $this->flowId) {
            if (!$this->flowId->equals($flowId)) {
                $this->duplicateFlow($flowId, $at);
            }

            return;
        }

        $this->flowId = $flowId;
        $this->submittedAt ??= $at;

        // A status came before the receipt: the platform had the file already.
        if (!$this->platformHasIt()) {
            $this->submitted($at);
        }
    }

    public function markBlocked(BlockReason $reason, ?string $detail, \DateTimeImmutable $now): void
    {
        if (!\in_array($this->state, [TransmissionState::PREPARED, TransmissionState::SUBMITTING], true)) {
            return;
        }

        $this->block($reason, $detail, $now);
    }

    /**
     * A credit note whose invoice is not on the platform yet: it waits,
     * without counting the attempts made so far — none of them reached the
     * platform.
     */
    public function awaitPrecedingInvoice(\DateTimeImmutable $now): void
    {
        if (TransmissionKind::CREDIT_NOTE !== $this->kind || TransmissionState::PREPARED !== $this->state) {
            return;
        }

        $this->state                 = TransmissionState::AWAITING_INVOICE;
        $this->submissionAttempts    = 0;
        $this->submissionRequestedAt = null;

        $this->changed(TransmissionAwaitingInvoice::class, $now);
    }

    /**
     * Its invoice was submitted: the credit note is ready to be sent.
     */
    public function releaseFromPrecedingInvoice(\DateTimeImmutable $now): void
    {
        if (TransmissionState::AWAITING_INVOICE !== $this->state) {
            return;
        }

        $this->state = TransmissionState::PREPARED;

        $this->changed(TransmissionPrepared::class, $now);
    }

    /**
     * Its invoice was refused or rejected ($invoiceStatus, 210 or 213): it no
     * longer exists for the tax authority, and the credit note stays internal.
     */
    public function markInternal(LifecycleStatusCode $invoiceStatus, \DateTimeImmutable $now): void
    {
        if (!\in_array($this->state, [TransmissionState::PREPARED, TransmissionState::AWAITING_INVOICE], true)) {
            return;
        }

        $this->state           = TransmissionState::NOT_REQUIRED;
        $this->internalBecause = $invoiceStatus;
        $this->detail          = \sprintf('The credited invoice was %s (%d).', LifecycleStatusCode::REFUSED === $invoiceStatus ? 'refused' : 'rejected', $invoiceStatus->value);

        $this->changed(TransmissionNotRequired::class, $now);
    }

    /**
     * Records a status the platform reported, whatever the local state; the
     * same status from the same flow is ignored.
     */
    public function recordLifecycleStatus(LifecycleStatusCode $code, ?string $reasonCode, ?string $reasonText, FlowId $sourceFlowId, \DateTimeImmutable $at): void
    {
        $event = new LifecycleEvent($code, $reasonCode, $reasonText, $sourceFlowId->toString(), $at);

        foreach ($this->lifecycle as $known) {
            if ($known->sameAs($event)) {
                return;
            }
        }

        // A status proves the platform has the file. It comes in a flow of its
        // own: the deposit flow id is left to the receipt.
        if (!$this->platformHasIt() && !$this->isDuplicateFlow()) {
            $this->submittedAt ??= $at;
            $this->submitted($at);
        }

        $this->lifecycle[] = $event;

        if (LifecycleStatusCode::INADMISSIBLE === $code && !$this->isDuplicateFlow()) {
            $this->state = TransmissionState::INADMISSIBLE;
        } elseif (TransmissionState::SUBMITTED === $this->state) {
            $this->state = TransmissionState::ACCEPTED;
        }

        $this->changed(LifecycleStatusRecorded::class, $at);
    }

    /**
     * The most recent status reported (ties: the higher code).
     */
    public function currentStatus(): ?LifecycleEvent
    {
        $current = null;

        foreach ($this->lifecycle as $event) {
            $later = null === $current
                || $event->at > $current->at
                || (0 === ($event->at <=> $current->at) && $event->code->value > $current->code->value);

            if ($later) {
                $current = $event;
            }
        }

        return $current;
    }

    /**
     * The status by which the buyer refused (210) or rejected (213) the
     * document, if it did: the document no longer exists for the tax authority.
     */
    public function refusedOrRejected(): ?LifecycleStatusCode
    {
        foreach ($this->lifecycle as $event) {
            if (\in_array($event->code, [LifecycleStatusCode::REFUSED, LifecycleStatusCode::REJECTED], true)) {
                return $event->code;
            }
        }

        return null;
    }

    /**
     * Whether the platform has the document or reported on it.
     */
    public function wasAccepted(): bool
    {
        return \in_array($this->state, [TransmissionState::SUBMITTED, TransmissionState::ACCEPTED], true);
    }

    /**
     * What the issuing context shows: pending_connection, queued,
     * awaiting_invoice, submitting, submitted, accepted, inadmissible, blocked
     * or not_required (an internal credit note).
     */
    public function viewState(): string
    {
        if (TransmissionState::PREPARED === $this->state) {
            return null === $this->connectionId ? 'pending_connection' : 'queued';
        }

        return $this->state->value;
    }

    public function requiresAction(): bool
    {
        if (\in_array($this->state, [TransmissionState::BLOCKED, TransmissionState::INADMISSIBLE], true)) {
            return true;
        }

        return true === $this->currentStatus()?->code->requiresAction();
    }

    public function id(): OutboundTransmissionId
    {
        return $this->id;
    }

    public function clinicId(): string
    {
        return $this->clinicId;
    }

    public function kind(): TransmissionKind
    {
        return $this->kind;
    }

    public function trackingId(): TrackingId
    {
        return $this->trackingId;
    }

    public function documentNumber(): string
    {
        return $this->documentNumber;
    }

    public function documentDate(): \DateTimeImmutable
    {
        return $this->documentDate;
    }

    public function sellerSiren(): Siren
    {
        return $this->sellerSiren;
    }

    public function buyerSiren(): ?Siren
    {
        return $this->buyerSiren;
    }

    public function precedingDocumentNumber(): ?string
    {
        return $this->precedingDocumentNumber;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    public function connectionId(): ?PlatformConnectionId
    {
        return $this->connectionId;
    }

    public function state(): TransmissionState
    {
        return $this->state;
    }

    public function blockReason(): ?BlockReason
    {
        return $this->blockReason;
    }

    /** Why it was blocked or not required. */
    public function detail(): ?string
    {
        return $this->detail;
    }

    public function document(): ?DocumentReference
    {
        return $this->document;
    }

    public function addressUsed(): ?ElectronicAddress
    {
        return $this->addressUsed;
    }

    public function flowId(): ?FlowId
    {
        return $this->flowId;
    }

    public function submissionRequestedAt(): ?\DateTimeImmutable
    {
        return $this->submissionRequestedAt;
    }

    public function submissionAttempts(): int
    {
        return $this->submissionAttempts;
    }

    public function submittedAt(): ?\DateTimeImmutable
    {
        return $this->submittedAt;
    }

    /**
     * @return list<LifecycleEvent>
     */
    public function lifecycle(): array
    {
        return $this->lifecycle;
    }

    /** Why a credit note stayed internal: its invoice's refusal (210) or rejection (213). */
    public function internalBecause(): ?LifecycleStatusCode
    {
        return $this->internalBecause;
    }

    /** Incremented on every change: the sequence of the events it publishes. */
    public function sequence(): int
    {
        return $this->sequence;
    }

    public function preparedAt(): \DateTimeImmutable
    {
        return $this->preparedAt;
    }

    private function queued(PlatformConnectionId $connectionId, \DateTimeImmutable $now): bool
    {
        if ($this->submissionAttempts > self::MAX_SUBMISSION_ATTEMPTS) {
            $this->block(BlockReason::SUBMISSION_ATTEMPTS_EXHAUSTED, \sprintf('%d submission attempts.', $this->submissionAttempts - 1), $now);

            return false;
        }

        $this->connectionId = $connectionId;

        $this->changed(TransmissionQueued::class, $now);

        return true;
    }

    /**
     * Whether the platform holds the file, by its receipt or a status.
     */
    private function platformHasIt(): bool
    {
        return \in_array($this->state, [TransmissionState::SUBMITTED, TransmissionState::ACCEPTED, TransmissionState::INADMISSIBLE], true);
    }

    private function isDuplicateFlow(): bool
    {
        return TransmissionState::BLOCKED === $this->state && BlockReason::DUPLICATE_FLOW === $this->blockReason;
    }

    /**
     * Whatever Kiveto thought — waiting, blocked, internal — the platform
     * has the file.
     */
    private function submitted(\DateTimeImmutable $at): void
    {
        $this->state           = TransmissionState::SUBMITTED;
        $this->blockReason     = null;
        $this->detail          = null;
        $this->internalBecause = null;

        $this->changed(TransmissionSubmitted::class, $at);
    }

    private function duplicateFlow(FlowId $flowId, \DateTimeImmutable $at): void
    {
        $detail = \sprintf('Flow %s received for a document already submitted as %s.', $flowId->toString(), (string) $this->flowId?->toString());

        if ($this->isDuplicateFlow()) {
            if (str_contains((string) $this->detail, $flowId->toString())) {
                return;
            }

            $detail = $this->detail . ' ' . $detail;
        }

        $this->block(BlockReason::DUPLICATE_FLOW, $detail, $at);
    }

    private function block(BlockReason $reason, ?string $detail, \DateTimeImmutable $now): void
    {
        $this->state       = TransmissionState::BLOCKED;
        $this->blockReason = $reason;
        $this->detail      = $detail;

        $this->changed(TransmissionBlocked::class, $now);
    }

    /**
     * @param class-string<TransmissionPrepared|TransmissionQueued|TransmissionAwaitingInvoice|TransmissionSubmitting|TransmissionSubmitted|TransmissionBlocked|TransmissionNotRequired|LifecycleStatusRecorded> $event
     */
    private function changed(string $event, \DateTimeImmutable $now): void
    {
        ++$this->sequence;

        /** @var DomainEventInterface $instance */
        $instance = new $event(
            $this->id->toString(),
            $this->clinicId,
            $this->trackingId->toString(),
            $this->sequence,
            $now->format(\DateTimeInterface::ATOM),
        );

        $this->recordDomainEvent($instance);
    }
}
