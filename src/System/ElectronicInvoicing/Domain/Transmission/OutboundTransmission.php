<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Domain\Transmission;

use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Event\DomainEventInterface;
use App\Shared\Domain\ValueObject\Siren;
use App\System\ElectronicInvoicing\Domain\Connection\PlatformConnectionId;
use App\System\ElectronicInvoicing\Domain\Event\LifecycleStatusRecorded;
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
 * answers with the same flow id. A status reported while still submitting
 * proves the platform received the file: the transmission moves to
 * submitted first, its flow id coming with the receipt. Duplicates are ignored, never an error that would make a
 * message loop.
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
        private int $version,
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
        int $version,
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
            $version,
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
     * The platform received the file under $flowId. The same flow id again is
     * a no-op; another flow id for a document already submitted is a double
     * deposit and blocks the transmission.
     */
    public function markSubmitted(FlowId $flowId, \DateTimeImmutable $at): void
    {
        // A status arrived before the receipt: the platform had the file, its
        // flow id comes now.
        $received = [TransmissionState::SUBMITTED, TransmissionState::ACCEPTED, TransmissionState::INADMISSIBLE];

        if (null === $this->flowId && \in_array($this->state, $received, true)) {
            $this->flowId = $flowId;
            ++$this->version;

            return;
        }

        if (null !== $this->flowId) {
            if (!$this->flowId->equals($flowId) && TransmissionState::BLOCKED !== $this->state) {
                $this->block(BlockReason::DUPLICATE_FLOW, \sprintf('Flow %s received for a document already submitted as %s.', $flowId->toString(), $this->flowId->toString()), $at);
            }

            return;
        }

        if (TransmissionState::SUBMITTING !== $this->state) {
            return;
        }

        $this->state       = TransmissionState::SUBMITTED;
        $this->flowId      = $flowId;
        $this->submittedAt = $at;

        $this->changed(TransmissionSubmitted::class, $at);
    }

    public function markBlocked(BlockReason $reason, ?string $detail, \DateTimeImmutable $now): void
    {
        if (!\in_array($this->state, [TransmissionState::PREPARED, TransmissionState::SUBMITTING], true)) {
            return;
        }

        $this->block($reason, $detail, $now);
    }

    public function markNotRequired(string $reason, \DateTimeImmutable $now): void
    {
        if (TransmissionState::PREPARED !== $this->state) {
            return;
        }

        $this->state  = TransmissionState::NOT_REQUIRED;
        $this->detail = $reason;

        $this->changed(TransmissionNotRequired::class, $now);
    }

    /**
     * Records a status the platform reported; the same status from the same
     * flow is ignored.
     */
    public function recordLifecycleStatus(LifecycleStatusCode $code, ?string $reasonCode, ?string $reasonText, FlowId $sourceFlowId, \DateTimeImmutable $at): void
    {
        $event = new LifecycleEvent($code, $reasonCode, $reasonText, $sourceFlowId->toString(), $at);

        foreach ($this->lifecycle as $known) {
            if ($known->sameAs($event)) {
                return;
            }
        }

        // A status proves the platform received the file. It comes in a flow of
        // its own: the deposit flow id is left to the receipt.
        if (TransmissionState::SUBMITTING === $this->state) {
            $this->state       = TransmissionState::SUBMITTED;
            $this->submittedAt = $at;

            $this->changed(TransmissionSubmitted::class, $at);
        }

        $this->lifecycle[] = $event;

        if (LifecycleStatusCode::INADMISSIBLE === $code) {
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
                || ($event->at === $current->at && $event->code->value > $current->code->value);

            if ($later) {
                $current = $event;
            }
        }

        return $current;
    }

    /**
     * False once the document was refused, rejected or cancelled.
     */
    public function isFiscallyAlive(): bool
    {
        foreach ($this->lifecycle as $event) {
            if ($event->code->voidsDocument()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether the platform has the document or reported on it.
     */
    public function wasAccepted(): bool
    {
        return \in_array($this->state, [TransmissionState::SUBMITTED, TransmissionState::ACCEPTED], true);
    }

    /**
     * What the issuing context shows: pending_connection, queued, submitting,
     * submitted, accepted, inadmissible, blocked or not_required.
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

    /** Incremented on every change: the sequence of the events it publishes. */
    public function version(): int
    {
        return $this->version;
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

    private function block(BlockReason $reason, ?string $detail, \DateTimeImmutable $now): void
    {
        $this->state       = TransmissionState::BLOCKED;
        $this->blockReason = $reason;
        $this->detail      = $detail;

        $this->changed(TransmissionBlocked::class, $now);
    }

    /**
     * @param class-string<TransmissionPrepared|TransmissionQueued|TransmissionSubmitting|TransmissionSubmitted|TransmissionBlocked|TransmissionNotRequired|LifecycleStatusRecorded> $event
     */
    private function changed(string $event, \DateTimeImmutable $now): void
    {
        ++$this->version;

        /** @var DomainEventInterface $instance */
        $instance = new $event(
            $this->id->toString(),
            $this->clinicId,
            $this->trackingId->toString(),
            $this->version,
            $now->format(\DateTimeInterface::ATOM),
        );

        $this->recordDomainEvent($instance);
    }
}
