<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\ElectronicInvoicing\Domain;

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
use App\System\ElectronicInvoicing\Domain\Transmission\BlockReason;
use App\System\ElectronicInvoicing\Domain\Transmission\DocumentReference;
use App\System\ElectronicInvoicing\Domain\Transmission\ElectronicAddress;
use App\System\ElectronicInvoicing\Domain\Transmission\FlowId;
use App\System\ElectronicInvoicing\Domain\Transmission\LifecycleStatusCode;
use App\System\ElectronicInvoicing\Domain\Transmission\OutboundTransmission;
use App\System\ElectronicInvoicing\Domain\Transmission\OutboundTransmissionId;
use App\System\ElectronicInvoicing\Domain\Transmission\TrackingId;
use App\System\ElectronicInvoicing\Domain\Transmission\TransmissionKind;
use App\System\ElectronicInvoicing\Domain\Transmission\TransmissionState;
use PHPUnit\Framework\TestCase;

final class OutboundTransmissionTest extends TestCase
{
    private const string CONNECTION = '01960000-0000-7000-8000-0000000ee001';

    public function testAPreparedTransmissionWaitsForAConnection(): void
    {
        $transmission = $this->prepared();

        self::assertSame(TransmissionState::PREPARED, $transmission->state());
        self::assertSame('pending_connection', $transmission->viewState());
        self::assertSame(1, $transmission->sequence());
        self::assertFalse($transmission->requiresAction());
        self::assertNull($transmission->currentStatus());
        self::assertNull($transmission->refusedOrRejected());
        self::assertNull($transmission->internalBecause());
        self::assertFalse($transmission->wasAccepted());
        self::assertSame('doc-1', $transmission->trackingId()->toString());
        self::assertSame(TransmissionKind::INVOICE, $transmission->kind());
        self::assertSame('PAR-2026-000001', $transmission->documentNumber());
        self::assertSame('2026-10-21', $transmission->documentDate()->format('Y-m-d'));
        self::assertSame('812456903', $transmission->sellerSiren()->value());
        self::assertSame('523456788', $transmission->buyerSiren()?->value());
        self::assertNull($transmission->precedingDocumentNumber());
        self::assertSame(['number' => 'PAR-2026-000001'], $transmission->payload());
        self::assertSame('01960000-0000-7000-8000-0000000ee0c1', $transmission->clinicId());
        self::assertSame('2026-10-21 10:00', $transmission->preparedAt()->format('Y-m-d H:i'));
        self::assertInstanceOf(TransmissionPrepared::class, $transmission->pullDomainEvents()[0]);
    }

    public function testRequestingASubmissionQueuesItOnTheConnection(): void
    {
        $transmission = $this->prepared();

        self::assertTrue($transmission->requestSubmission($this->connection(), $this->now()));
        self::assertSame('queued', $transmission->viewState());
        self::assertSame(self::CONNECTION, $transmission->connectionId()?->toString());
        self::assertSame(1, $transmission->submissionAttempts());
        self::assertNotNull($transmission->submissionRequestedAt());
        self::assertSame(2, $transmission->sequence());
        self::assertInstanceOf(TransmissionQueued::class, $transmission->pullDomainEvents()[1]);
    }

    public function testTheTenthFailedAttemptBlocksTheTransmission(): void
    {
        $transmission = $this->prepared();

        for ($i = 0; $i < OutboundTransmission::MAX_SUBMISSION_ATTEMPTS; ++$i) {
            self::assertTrue($transmission->requestSubmission($this->connection(), $this->now()));
        }

        self::assertFalse($transmission->requestSubmission($this->connection(), $this->now()));
        self::assertSame(TransmissionState::BLOCKED, $transmission->state());
        self::assertSame(BlockReason::SUBMISSION_ATTEMPTS_EXHAUSTED, $transmission->blockReason());
        self::assertTrue($transmission->requiresAction());
        self::assertFalse($transmission->requestSubmission($this->connection(), $this->now()));
        self::assertFalse($transmission->acknowledgeSubmissionRequested($this->connection(), $this->now()));
    }

    public function testAnAttemptCountedInStorageIsAcknowledged(): void
    {
        $transmission = $this->prepared();

        self::assertTrue($transmission->acknowledgeSubmissionRequested($this->connection(), $this->now()));
        self::assertSame(0, $transmission->submissionAttempts());
        self::assertSame('queued', $transmission->viewState());
    }

    public function testTheFileIsFrozenThenTheFlowRecorded(): void
    {
        $transmission = $this->submitting();

        self::assertSame(TransmissionState::SUBMITTING, $transmission->state());
        self::assertSame('submitting', $transmission->viewState());
        $document = $transmission->document();
        $address  = $transmission->addressUsed();
        self::assertNotNull($document);
        self::assertNotNull($address);
        self::assertSame(str_repeat('a', 64), $document->sha256());
        self::assertSame('storage/doc-1.xml', $document->storageId());
        self::assertSame('0225', $address->scheme());
        self::assertSame('523456788', $address->identifier());

        // Submitting again keeps the frozen file.
        $transmission->markSubmitting(DocumentReference::of('other', str_repeat('b', 64)), ElectronicAddress::of('0225', 'x'), $this->now());
        self::assertSame('storage/doc-1.xml', $transmission->document()?->storageId());

        $transmission->markSubmitted(FlowId::fromString('flow-1'), $this->now());

        self::assertSame(TransmissionState::SUBMITTED, $transmission->state());
        self::assertSame('flow-1', $transmission->flowId()?->toString());
        self::assertNotNull($transmission->submittedAt());
        self::assertTrue($transmission->wasAccepted());

        $events = array_map(static fn (object $e): string => $e::class, $transmission->pullDomainEvents());
        self::assertContains(TransmissionSubmitting::class, $events);
        self::assertContains(TransmissionSubmitted::class, $events);
    }

    public function testOnlyAPreparedTransmissionStartsSubmitting(): void
    {
        $transmission = $this->prepared();
        $transmission->markBlocked(BlockReason::RENDERING_FAILED, 'bad', $this->now());

        $this->expectException(InvalidTransmissionTransitionException::class);

        $transmission->markSubmitting(DocumentReference::of('x', str_repeat('a', 64)), ElectronicAddress::of('0225', 'x'), $this->now());
    }

    public function testTheSameFlowAgainIsANoOpAndAnotherIsADoubleDeposit(): void
    {
        $transmission = $this->submitting();
        $transmission->markSubmitted(FlowId::fromString('flow-1'), $this->now());
        $version = $transmission->sequence();

        $transmission->markSubmitted(FlowId::fromString('flow-1'), $this->now());
        self::assertSame($version, $transmission->sequence());

        $transmission->markSubmitted(FlowId::fromString('flow-2'), $this->now());
        self::assertSame(TransmissionState::BLOCKED, $transmission->state());
        self::assertSame(BlockReason::DUPLICATE_FLOW, $transmission->blockReason());
        self::assertStringContainsString('flow-2', (string) $transmission->detail());

        // Once blocked, a third flow is still kept — once.
        $transmission->markSubmitted(FlowId::fromString('flow-3'), $this->now());
        $sequence = $transmission->sequence();
        $transmission->markSubmitted(FlowId::fromString('flow-3'), $this->now());
        self::assertSame(BlockReason::DUPLICATE_FLOW, $transmission->blockReason());
        self::assertStringContainsString('flow-2', (string) $transmission->detail());
        self::assertStringContainsString('flow-3', (string) $transmission->detail());
        self::assertSame($sequence, $transmission->sequence());
    }

    /**
     * F6: a receipt is recorded whatever the local state — the platform has
     * the file, a local block does not stand against it.
     */
    public function testAReceiptIsRecordedWhateverTheLocalState(): void
    {
        $prepared = $this->prepared();
        $prepared->markSubmitted(FlowId::fromString('flow-1'), $this->now());

        self::assertSame(TransmissionState::SUBMITTED, $prepared->state());
        self::assertSame('flow-1', $prepared->flowId()?->toString());

        $blocked = $this->submitting();
        $blocked->markBlocked(BlockReason::PLATFORM_REFUSED, 'HTTP 400', $this->now());
        $blocked->markSubmitted(FlowId::fromString('flow-2'), $this->at('10:30'));

        self::assertSame(TransmissionState::SUBMITTED, $blocked->state());
        self::assertSame('flow-2', $blocked->flowId()?->toString());
        self::assertNull($blocked->blockReason());
        self::assertNull($blocked->detail());
        self::assertFalse($blocked->requiresAction());
        self::assertSame('2026-10-21 10:30', $blocked->submittedAt()?->format('Y-m-d H:i'));
        self::assertInstanceOf(TransmissionSubmitted::class, array_last($blocked->pullDomainEvents()));
    }

    public function testAStatusIsRecordedWhateverTheLocalState(): void
    {
        $blocked = $this->prepared();
        $blocked->requestSubmission($this->connection(), $this->now());
        $blocked->markBlocked(BlockReason::SUBMISSION_ATTEMPTS_EXHAUSTED, '10 submission attempts.', $this->now());
        $blocked->recordLifecycleStatus(LifecycleStatusCode::DEPOSITED, null, null, FlowId::fromString('cdar-1'), $this->now());

        self::assertSame(TransmissionState::ACCEPTED, $blocked->state());
        self::assertNull($blocked->blockReason());
        self::assertCount(1, $blocked->lifecycle());

        // A double deposit stays an alarm, its statuses kept all the same.
        $duplicate = $this->submitting();
        $duplicate->markSubmitted(FlowId::fromString('flow-1'), $this->now());
        $duplicate->markSubmitted(FlowId::fromString('flow-2'), $this->now());
        $duplicate->recordLifecycleStatus(LifecycleStatusCode::APPROVED, null, null, FlowId::fromString('cdar-2'), $this->now());
        $duplicate->recordLifecycleStatus(LifecycleStatusCode::INADMISSIBLE, null, null, FlowId::fromString('cdar-3'), $this->now());

        self::assertSame(TransmissionState::BLOCKED, $duplicate->state());
        self::assertSame(BlockReason::DUPLICATE_FLOW, $duplicate->blockReason());
        self::assertCount(2, $duplicate->lifecycle());
    }

    public function testBlockingOnlyAppliesBeforeSubmission(): void
    {
        $blocked = $this->prepared();
        $blocked->markBlocked(BlockReason::RENDERING_FAILED, 'bad', $this->now());

        self::assertSame('blocked', $blocked->viewState());
        self::assertSame('bad', $blocked->detail());
        self::assertInstanceOf(TransmissionBlocked::class, $blocked->pullDomainEvents()[1]);

        $submitted = $this->submitting();
        $submitted->markSubmitted(FlowId::fromString('flow-1'), $this->now());
        $submitted->markBlocked(BlockReason::PLATFORM_REFUSED, null, $this->now());
        self::assertSame(TransmissionState::SUBMITTED, $submitted->state());
    }

    /**
     * F4: a credit note waits for its invoice without counting an attempt,
     * and goes once released.
     */
    public function testACreditNoteWaitsForItsInvoiceWithoutCountingAnAttempt(): void
    {
        $creditNote = $this->creditNote();
        $creditNote->requestSubmission($this->connection(), $this->now());
        $creditNote->awaitPrecedingInvoice($this->now());

        self::assertSame(TransmissionState::AWAITING_INVOICE, $creditNote->state());
        self::assertSame('awaiting_invoice', $creditNote->viewState());
        self::assertSame(0, $creditNote->submissionAttempts());
        self::assertNull($creditNote->submissionRequestedAt());
        self::assertFalse($creditNote->requiresAction());
        self::assertInstanceOf(TransmissionAwaitingInvoice::class, array_last($creditNote->pullDomainEvents()));

        // Waiting already: nothing more.
        $sequence = $creditNote->sequence();
        $creditNote->awaitPrecedingInvoice($this->now());
        self::assertSame($sequence, $creditNote->sequence());

        $creditNote->releaseFromPrecedingInvoice($this->now());
        self::assertSame(TransmissionState::PREPARED, $creditNote->state());
        self::assertInstanceOf(TransmissionPrepared::class, array_last($creditNote->pullDomainEvents()));
        $creditNote->releaseFromPrecedingInvoice($this->now());
        self::assertSame($sequence + 1, $creditNote->sequence());

        // An invoice never waits.
        $invoice = $this->prepared();
        $invoice->awaitPrecedingInvoice($this->now());
        self::assertSame(TransmissionState::PREPARED, $invoice->state());
    }

    /**
     * F5: a credit note stays internal only when its invoice was refused or
     * rejected — and still, the platform's word wins.
     */
    public function testACreditNoteOfARefusedOrRejectedInvoiceStaysInternal(): void
    {
        $creditNote = $this->creditNote();
        $creditNote->awaitPrecedingInvoice($this->now());
        $creditNote->markInternal(LifecycleStatusCode::REJECTED, $this->now());

        self::assertSame(TransmissionState::NOT_REQUIRED, $creditNote->state());
        self::assertSame('not_required', $creditNote->viewState());
        self::assertSame(LifecycleStatusCode::REJECTED, $creditNote->internalBecause());
        self::assertSame('The credited invoice was rejected (213).', $creditNote->detail());
        self::assertFalse($creditNote->requiresAction());
        self::assertInstanceOf(TransmissionNotRequired::class, array_last($creditNote->pullDomainEvents()));

        $refused = $this->creditNote();
        $refused->markInternal(LifecycleStatusCode::REFUSED, $this->now());
        self::assertSame('The credited invoice was refused (210).', $refused->detail());

        // Sent already: it stays sent.
        $sent = $this->submitting();
        $sent->markInternal(LifecycleStatusCode::REFUSED, $this->now());
        self::assertSame(TransmissionState::SUBMITTING, $sent->state());

        // The platform reports on it after all: it has it.
        $creditNote->recordLifecycleStatus(LifecycleStatusCode::DEPOSITED, null, null, FlowId::fromString('cdar-1'), $this->now());
        self::assertSame(TransmissionState::ACCEPTED, $creditNote->state());
        self::assertNull($creditNote->internalBecause());
    }

    public function testLifecycleStatusesAreRecordedOnceAndTheLatestWins(): void
    {
        $transmission = $this->submitting();
        $transmission->markSubmitted(FlowId::fromString('flow-1'), $this->now());

        $transmission->recordLifecycleStatus(LifecycleStatusCode::DEPOSITED, null, null, FlowId::fromString('cdar-1'), $this->at('10:01'));
        self::assertSame(TransmissionState::ACCEPTED, $transmission->state());

        $transmission->recordLifecycleStatus(LifecycleStatusCode::APPROVED, null, null, FlowId::fromString('cdar-3'), $this->at('10:05'));
        $transmission->recordLifecycleStatus(LifecycleStatusCode::MADE_AVAILABLE, null, null, FlowId::fromString('cdar-2'), $this->at('10:03'));
        $version = $transmission->sequence();
        $transmission->recordLifecycleStatus(LifecycleStatusCode::APPROVED, null, null, FlowId::fromString('cdar-3'), $this->at('10:05'));

        self::assertSame($version, $transmission->sequence());
        self::assertCount(3, $transmission->lifecycle());
        self::assertSame(LifecycleStatusCode::APPROVED, $transmission->currentStatus()?->code);
        self::assertSame('accepted', $transmission->viewState());
        self::assertFalse($transmission->requiresAction());
        self::assertInstanceOf(LifecycleStatusRecorded::class, array_last($transmission->pullDomainEvents()));
    }

    public function testOnATieTheHigherStatusWins(): void
    {
        $transmission = $this->submitting();
        // Same instant, distinct instances: compared by value.
        $transmission->recordLifecycleStatus(LifecycleStatusCode::RECEIVED, null, null, FlowId::fromString('cdar-1'), $this->at('10:05'));
        $transmission->recordLifecycleStatus(LifecycleStatusCode::DISPUTED, 'TX_TVA_ERR', 'Taux de TVA erroné', FlowId::fromString('cdar-2'), $this->at('10:05'));
        $transmission->recordLifecycleStatus(LifecycleStatusCode::MADE_AVAILABLE, null, null, FlowId::fromString('cdar-3'), $this->at('10:05'));

        self::assertSame(LifecycleStatusCode::DISPUTED, $transmission->currentStatus()?->code);
        self::assertTrue($transmission->requiresAction());
    }

    public function testAStatusWhileSubmittingProvesTheDeposit(): void
    {
        $transmission = $this->submitting();

        $transmission->recordLifecycleStatus(LifecycleStatusCode::DEPOSITED, null, null, FlowId::fromString('flow-9-01'), $this->now());

        self::assertNull($transmission->flowId());
        self::assertSame(TransmissionState::ACCEPTED, $transmission->state());
        self::assertNotNull($transmission->submittedAt());

        // The receipt comes later with the deposit flow id: no double deposit,
        // and no change to report.
        $sequence = $transmission->sequence();
        $transmission->markSubmitted(FlowId::fromString('flow-9'), $this->now());

        self::assertSame('flow-9', $transmission->flowId()?->toString());
        self::assertSame(TransmissionState::ACCEPTED, $transmission->state());
        self::assertSame($sequence, $transmission->sequence());
    }

    public function testAnInadmissibleFileStaysInadmissible(): void
    {
        $transmission = $this->submitting();
        $transmission->recordLifecycleStatus(LifecycleStatusCode::INADMISSIBLE, 'IRR_SYNTAX', null, FlowId::fromString('flow-1'), $this->at('10:01'));
        $transmission->recordLifecycleStatus(LifecycleStatusCode::DEPOSITED, null, null, FlowId::fromString('flow-1'), $this->at('10:02'));

        self::assertSame(TransmissionState::INADMISSIBLE, $transmission->state());
        self::assertTrue($transmission->requiresAction());
        self::assertFalse($transmission->wasAccepted());
    }

    public function testARefusedOrRejectedDocumentSaysSo(): void
    {
        $transmission = $this->submitting();
        $transmission->recordLifecycleStatus(LifecycleStatusCode::CANCELLED, null, null, FlowId::fromString('flow-0'), $this->now());
        self::assertNull($transmission->refusedOrRejected());

        $transmission->recordLifecycleStatus(LifecycleStatusCode::REFUSED, 'DOUBLON', null, FlowId::fromString('flow-1'), $this->now());
        self::assertSame(LifecycleStatusCode::REFUSED, $transmission->refusedOrRejected());
    }

    public function testRequestingAnAlreadySubmittedTransmissionDoesNothing(): void
    {
        $transmission = $this->submitting();
        $transmission->markSubmitted(FlowId::fromString('flow-1'), $this->now());

        self::assertFalse($transmission->requestSubmission($this->connection(), $this->now()));
        self::assertFalse($transmission->acknowledgeSubmissionRequested($this->connection(), $this->now()));
    }

    public function testItIsReconstitutedAsStored(): void
    {
        $transmission = OutboundTransmission::reconstitute(
            id: OutboundTransmissionId::fromString('01960000-0000-7000-8000-0000000ee0a1'),
            clinicId: '01960000-0000-7000-8000-0000000ee0c1',
            kind: TransmissionKind::CREDIT_NOTE,
            trackingId: TrackingId::fromString('cn-1'),
            documentNumber: 'AV-1',
            documentDate: $this->now(),
            sellerSiren: Siren::fromInput('812456903'),
            buyerSiren: null,
            precedingDocumentNumber: 'PAR-1',
            payload: [],
            connectionId: $this->connection(),
            state: TransmissionState::SUBMITTED,
            blockReason: null,
            detail: null,
            document: null,
            addressUsed: null,
            flowId: FlowId::fromString('flow-1'),
            submissionRequestedAt: $this->now(),
            submissionAttempts: 2,
            submittedAt: $this->now(),
            lifecycle: [],
            internalBecause: LifecycleStatusCode::REFUSED,
            sequence: 7,
            preparedAt: $this->now(),
        );

        self::assertSame(7, $transmission->sequence());
        self::assertSame(LifecycleStatusCode::REFUSED, $transmission->internalBecause());
        self::assertSame('PAR-1', $transmission->precedingDocumentNumber());
        self::assertNull($transmission->buyerSiren());
        self::assertSame([], $transmission->pullDomainEvents());
    }

    private function prepared(): OutboundTransmission
    {
        return OutboundTransmission::prepare(
            id: OutboundTransmissionId::fromString('01960000-0000-7000-8000-0000000ee0a1'),
            clinicId: '01960000-0000-7000-8000-0000000ee0c1',
            kind: TransmissionKind::INVOICE,
            trackingId: TrackingId::fromString('doc-1'),
            documentNumber: 'PAR-2026-000001',
            documentDate: new \DateTimeImmutable('2026-10-21'),
            sellerSiren: Siren::fromInput('812456903'),
            buyerSiren: Siren::fromInput('523456788'),
            precedingDocumentNumber: null,
            payload: ['number' => 'PAR-2026-000001'],
            now: $this->now(),
        );
    }

    private function creditNote(): OutboundTransmission
    {
        return OutboundTransmission::prepare(
            id: OutboundTransmissionId::fromString('01960000-0000-7000-8000-0000000ee0a2'),
            clinicId: '01960000-0000-7000-8000-0000000ee0c1',
            kind: TransmissionKind::CREDIT_NOTE,
            trackingId: TrackingId::fromString('cn-1'),
            documentNumber: 'AVPAR-2026-000001',
            documentDate: new \DateTimeImmutable('2026-10-22'),
            sellerSiren: Siren::fromInput('812456903'),
            buyerSiren: Siren::fromInput('523456788'),
            precedingDocumentNumber: 'PAR-2026-000001',
            payload: ['number' => 'AVPAR-2026-000001'],
            now: $this->now(),
        );
    }

    private function submitting(): OutboundTransmission
    {
        $transmission = $this->prepared();
        $transmission->requestSubmission($this->connection(), $this->now());
        $transmission->markSubmitting(DocumentReference::of('storage/doc-1.xml', str_repeat('a', 64)), ElectronicAddress::of('0225', '523456788'), $this->now());

        return $transmission;
    }

    private function connection(): PlatformConnectionId
    {
        return PlatformConnectionId::fromString(self::CONNECTION);
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-10-21 10:00:00');
    }

    private function at(string $time): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-10-21 ' . $time . ':00');
    }
}
