<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\ElectronicInvoicing\Domain;

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
        self::assertSame(1, $transmission->version());
        self::assertFalse($transmission->requiresAction());
        self::assertNull($transmission->currentStatus());
        self::assertTrue($transmission->isFiscallyAlive());
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
        self::assertSame(2, $transmission->version());
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
        $transmission->markNotRequired('void invoice', $this->now());

        $this->expectException(InvalidTransmissionTransitionException::class);

        $transmission->markSubmitting(DocumentReference::of('x', str_repeat('a', 64)), ElectronicAddress::of('0225', 'x'), $this->now());
    }

    public function testTheSameFlowAgainIsANoOpAndAnotherIsADoubleDeposit(): void
    {
        $transmission = $this->submitting();
        $transmission->markSubmitted(FlowId::fromString('flow-1'), $this->now());
        $version = $transmission->version();

        $transmission->markSubmitted(FlowId::fromString('flow-1'), $this->now());
        self::assertSame($version, $transmission->version());

        $transmission->markSubmitted(FlowId::fromString('flow-2'), $this->now());
        self::assertSame(TransmissionState::BLOCKED, $transmission->state());
        self::assertSame(BlockReason::DUPLICATE_FLOW, $transmission->blockReason());
        self::assertStringContainsString('flow-2', (string) $transmission->detail());

        // Once blocked, a third flow changes nothing.
        $transmission->markSubmitted(FlowId::fromString('flow-3'), $this->now());
        self::assertSame(BlockReason::DUPLICATE_FLOW, $transmission->blockReason());
    }

    public function testASubmittedReceiptForAPreparedTransmissionIsIgnored(): void
    {
        $transmission = $this->prepared();
        $transmission->markSubmitted(FlowId::fromString('flow-1'), $this->now());

        self::assertSame(TransmissionState::PREPARED, $transmission->state());
        self::assertNull($transmission->flowId());
    }

    public function testBlockingAndNotRequiredOnlyApplyBeforeSubmission(): void
    {
        $blocked = $this->prepared();
        $blocked->markBlocked(BlockReason::RENDERING_FAILED, 'bad', $this->now());

        self::assertSame('blocked', $blocked->viewState());
        self::assertSame('bad', $blocked->detail());
        self::assertInstanceOf(TransmissionBlocked::class, $blocked->pullDomainEvents()[1]);

        $submitted = $this->submitting();
        $submitted->markSubmitted(FlowId::fromString('flow-1'), $this->now());
        $submitted->markBlocked(BlockReason::PLATFORM_REFUSED, null, $this->now());
        $submitted->markNotRequired('x', $this->now());
        self::assertSame(TransmissionState::SUBMITTED, $submitted->state());

        $notRequired = $this->prepared();
        $notRequired->markNotRequired('void invoice', $this->now());
        self::assertSame('not_required', $notRequired->viewState());
        self::assertFalse($notRequired->requiresAction());
        self::assertInstanceOf(TransmissionNotRequired::class, $notRequired->pullDomainEvents()[1]);
    }

    public function testLifecycleStatusesAreRecordedOnceAndTheLatestWins(): void
    {
        $transmission = $this->submitting();
        $transmission->markSubmitted(FlowId::fromString('flow-1'), $this->now());

        $transmission->recordLifecycleStatus(LifecycleStatusCode::DEPOSITED, null, null, FlowId::fromString('cdar-1'), $this->at('10:01'));
        self::assertSame(TransmissionState::ACCEPTED, $transmission->state());

        $transmission->recordLifecycleStatus(LifecycleStatusCode::APPROVED, null, null, FlowId::fromString('cdar-3'), $this->at('10:05'));
        $transmission->recordLifecycleStatus(LifecycleStatusCode::MADE_AVAILABLE, null, null, FlowId::fromString('cdar-2'), $this->at('10:03'));
        $version = $transmission->version();
        $transmission->recordLifecycleStatus(LifecycleStatusCode::APPROVED, null, null, FlowId::fromString('cdar-3'), $this->at('10:05'));

        self::assertSame($version, $transmission->version());
        self::assertCount(3, $transmission->lifecycle());
        self::assertSame(LifecycleStatusCode::APPROVED, $transmission->currentStatus()?->code);
        self::assertSame('accepted', $transmission->viewState());
        self::assertFalse($transmission->requiresAction());
        self::assertInstanceOf(LifecycleStatusRecorded::class, array_last($transmission->pullDomainEvents()));
    }

    public function testOnATieTheHigherStatusWins(): void
    {
        $transmission = $this->submitting();
        $transmission->recordLifecycleStatus(LifecycleStatusCode::DISPUTED, 'TX_TVA_ERR', 'Taux de TVA erroné', FlowId::fromString('cdar-2'), $this->at('10:05'));
        $transmission->recordLifecycleStatus(LifecycleStatusCode::RECEIVED, null, null, FlowId::fromString('cdar-1'), $this->at('10:05'));

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

        // The receipt comes later with the deposit flow id: no double deposit.
        $version = $transmission->version();
        $transmission->markSubmitted(FlowId::fromString('flow-9'), $this->now());

        self::assertSame('flow-9', $transmission->flowId()?->toString());
        self::assertSame(TransmissionState::ACCEPTED, $transmission->state());
        self::assertSame($version + 1, $transmission->version());
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

    public function testARefusedDocumentIsNoLongerFiscallyAlive(): void
    {
        $transmission = $this->submitting();
        $transmission->recordLifecycleStatus(LifecycleStatusCode::REFUSED, 'DOUBLON', null, FlowId::fromString('flow-1'), $this->now());

        self::assertFalse($transmission->isFiscallyAlive());
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
            version: 7,
            preparedAt: $this->now(),
        );

        self::assertSame(7, $transmission->version());
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
