<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\ElectronicInvoicing\Application;

use App\Shared\Application\ExternalApi\ExternalServiceUnavailable;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\System\ElectronicInvoicing\Application\Command\RecordLifecycleStatus\RecordLifecycleStatus;
use App\System\ElectronicInvoicing\Application\Command\RecordLifecycleStatus\RecordLifecycleStatusHandler;
use App\System\ElectronicInvoicing\Application\Command\RecordSubmissionPrepared\RecordSubmissionPrepared;
use App\System\ElectronicInvoicing\Application\Command\RecordSubmissionPrepared\RecordSubmissionPreparedHandler;
use App\System\ElectronicInvoicing\Application\Command\RecordSubmissionReceipt\RecordSubmissionReceipt;
use App\System\ElectronicInvoicing\Application\Command\RecordSubmissionReceipt\RecordSubmissionReceiptHandler;
use App\System\ElectronicInvoicing\Application\Command\RecordTransmissionBlocked\RecordTransmissionBlocked;
use App\System\ElectronicInvoicing\Application\Command\RecordTransmissionBlocked\RecordTransmissionBlockedHandler;
use App\System\ElectronicInvoicing\Application\Command\RecordTransmissionNotRequired\RecordTransmissionNotRequired;
use App\System\ElectronicInvoicing\Application\Command\RecordTransmissionNotRequired\RecordTransmissionNotRequiredHandler;
use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\RequestInvoiceTransmission;
use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\RequestInvoiceTransmissionHandler;
use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\TransmittableInvoice;
use App\System\ElectronicInvoicing\Application\Command\SubmitTransmission\SubmitTransmission;
use App\System\ElectronicInvoicing\Application\Command\SubmitTransmission\SubmitTransmissionHandler;
use App\System\ElectronicInvoicing\Application\Port\DocumentStoreInterface;
use App\System\ElectronicInvoicing\Application\Port\InvoiceDocumentRendererInterface;
use App\System\ElectronicInvoicing\Domain\Connection\PlatformConnectionId;
use App\System\ElectronicInvoicing\Domain\Port\DirectoryEntry;
use App\System\ElectronicInvoicing\Domain\Port\DirectoryLookupInterface;
use App\System\ElectronicInvoicing\Domain\Port\FlowSubmitterInterface;
use App\System\ElectronicInvoicing\Domain\Transmission\BlockReason;
use App\System\ElectronicInvoicing\Domain\Transmission\DocumentReference;
use App\System\ElectronicInvoicing\Domain\Transmission\ElectronicAddress;
use App\System\ElectronicInvoicing\Domain\Transmission\FlowId;
use App\System\ElectronicInvoicing\Domain\Transmission\LifecycleStatusCode;
use App\System\ElectronicInvoicing\Domain\Transmission\OutboundTransmission;
use App\System\ElectronicInvoicing\Domain\Transmission\OutboundTransmissionId;
use App\System\ElectronicInvoicing\Domain\Transmission\TransmissionState;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\System\ElectronicInvoicing\Support\CapturingBuses;
use App\Tests\Unit\System\ElectronicInvoicing\Support\FakeDirectory;
use App\Tests\Unit\System\ElectronicInvoicing\Support\FakeRenderer;
use App\Tests\Unit\System\ElectronicInvoicing\Support\FakeStore;
use App\Tests\Unit\System\ElectronicInvoicing\Support\FakeSubmitter;
use App\Tests\Unit\System\ElectronicInvoicing\Support\InMemoryConnections;
use App\Tests\Unit\System\ElectronicInvoicing\Support\InMemoryTransmissions;
use App\Tests\Unit\System\ElectronicInvoicing\Support\TransmittableInvoices;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class TransmissionHandlersTest extends TestCase
{
    private InMemoryTransmissions $transmissions;
    private CapturingBuses $buses;
    private int $ids = 0;

    protected function setUp(): void
    {
        $this->transmissions = new InMemoryTransmissions();
        $this->buses         = new CapturingBuses();
    }

    // ── Request ──

    public function testWithoutAConnectionTheTransmissionWaits(): void
    {
        $id = $this->request(new InMemoryConnections(), TransmittableInvoices::invoice());

        $transmission = $this->transmission($id);
        self::assertSame('pending_connection', $transmission->viewState());
        self::assertSame([], $this->buses->external);
        self::assertSame(['number' => 'PAR-2026-000001'], ['number' => $transmission->payload()['number']]);
        self::assertNotEmpty($this->buses->events);
    }

    public function testWithAVerifiedConnectionTheSubmissionIsQueued(): void
    {
        $id = $this->request(InMemoryConnections::verified(TransmittableInvoices::SELLER), TransmittableInvoices::invoice());

        self::assertSame('queued', $this->transmission($id)->viewState());
        self::assertCount(1, $this->buses->external);
        self::assertInstanceOf(SubmitTransmission::class, $this->buses->external[0][0]);
    }

    public function testTheSameRequestTwiceGivesOneTransmission(): void
    {
        $connections = InMemoryConnections::verified(TransmittableInvoices::SELLER);
        $first       = $this->request($connections, TransmittableInvoices::invoice());
        $second      = $this->request($connections, TransmittableInvoices::invoice());

        self::assertSame($first, $second);
        self::assertCount(1, $this->transmissions->items);
        self::assertCount(1, $this->buses->external);
    }

    public function testACreditNoteOfAVoidInvoiceIsNotRequired(): void
    {
        $connections = InMemoryConnections::verified(TransmittableInvoices::SELLER);
        $invoice     = $this->transmission($this->request($connections, TransmittableInvoices::invoice()));
        $invoice->markSubmitting(DocumentReference::of('f', str_repeat('a', 64)), ElectronicAddress::of('0225', 'x'), $this->now());
        $invoice->recordLifecycleStatus(LifecycleStatusCode::REFUSED, 'DOUBLON', null, FlowId::fromString('flow-1'), $this->now());

        $note = $this->transmission($this->request($connections, TransmittableInvoices::creditNote()));

        self::assertSame(TransmissionState::NOT_REQUIRED, $note->state());
        self::assertCount(1, $this->buses->external);
    }

    public function testACreditNoteOfALiveInvoiceIsQueued(): void
    {
        $connections = InMemoryConnections::verified(TransmittableInvoices::SELLER);
        $this->request($connections, TransmittableInvoices::invoice());

        $note = $this->transmission($this->request($connections, TransmittableInvoices::creditNote()));

        self::assertSame('queued', $note->viewState());
    }

    // ── Submit ──

    public function testASubmissionRendersStoresThenDeposits(): void
    {
        $id        = $this->request(InMemoryConnections::verified(TransmittableInvoices::SELLER), TransmittableInvoices::invoice());
        $submitter = new FakeSubmitter();
        $store     = new FakeStore();

        $this->submitHandler($submitter, $store)(new SubmitTransmission($id));

        $prepared = $this->buses->commandsOf(RecordSubmissionPrepared::class)[0];
        self::assertSame(hash('sha256', '<xml/>'), $prepared->sha256);
        self::assertSame('0225', $prepared->electronicAddressScheme);
        self::assertSame(TransmittableInvoices::BUYER, $prepared->electronicAddressIdentifier);
        self::assertSame('<xml/>', $store->stored[hash('sha256', '<xml/>')]);
        self::assertSame('<xml/>', $submitter->flows[0]->content);
        self::assertSame('CII', $submitter->flows[0]->syntax);
        self::assertSame('B2B', $submitter->flows[0]->processingRule);
        self::assertSame('doc-1.xml', $submitter->flows[0]->fileName);
        self::assertSame('flow-doc-1', $this->buses->commandsOf(RecordSubmissionReceipt::class)[0]->flowId);
    }

    public function testTheDirectoryAddressIsUsedWhenItKnowsTheBuyer(): void
    {
        $id = $this->request(InMemoryConnections::verified(TransmittableInvoices::SELLER), TransmittableInvoices::invoice());

        $this->submitHandler(directory: new FakeDirectory(new DirectoryEntry(true, '0088', '3654789851')))(new SubmitTransmission($id));

        $prepared = $this->buses->commandsOf(RecordSubmissionPrepared::class)[0];
        self::assertSame('0088', $prepared->electronicAddressScheme);
        self::assertSame('3654789851', $prepared->electronicAddressIdentifier);
    }

    public function testAnUnreachableBuyerOrADownDirectoryKeepsTheDefaultAddress(): void
    {
        foreach ([new FakeDirectory(new DirectoryEntry(false, null, null)), new FakeDirectory(null)] as $i => $directory) {
            $this->buses = new CapturingBuses();
            $id          = $this->request(InMemoryConnections::verified(TransmittableInvoices::SELLER), TransmittableInvoices::invoice('doc-' . $i, 'PAR-' . $i));

            $this->submitHandler(directory: $directory)(new SubmitTransmission($id));

            self::assertSame('0225', $this->buses->commandsOf(RecordSubmissionPrepared::class)[0]->electronicAddressScheme);
        }
    }

    public function testARetryReloadsTheFrozenFile(): void
    {
        $id                                 = $this->request(InMemoryConnections::verified(TransmittableInvoices::SELLER), TransmittableInvoices::invoice());
        $store                              = new FakeStore();
        $store->stored[str_repeat('c', 64)] = '<frozen/>';
        $this->transmission($id)->markSubmitting(DocumentReference::of('stored', str_repeat('c', 64)), ElectronicAddress::of('0225', 'x'), $this->now());
        $submitter = new FakeSubmitter();

        $this->submitHandler($submitter, $store, renderer: new FakeRenderer(fail: true))(new SubmitTransmission($id));

        self::assertSame('<frozen/>', $submitter->flows[0]->content);
        self::assertSame(str_repeat('c', 64), $submitter->flows[0]->sha256);
        self::assertSame([], $this->buses->commandsOf(RecordSubmissionPrepared::class));
    }

    public function testARenderingFailureBlocksTheTransmission(): void
    {
        $id     = $this->request(InMemoryConnections::verified(TransmittableInvoices::SELLER), TransmittableInvoices::invoice());
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $this->submitHandler(renderer: new FakeRenderer(fail: true), logger: $logger)(new SubmitTransmission($id));

        $blocked = $this->buses->commandsOf(RecordTransmissionBlocked::class)[0];
        self::assertSame(BlockReason::RENDERING_FAILED->value, $blocked->reason);
        self::assertSame('line 1: broken', $blocked->detail);
    }

    public function testASellerOtherThanTheOrganisationIsBlocked(): void
    {
        $connections = InMemoryConnections::verified('523456788');
        $id          = $this->request(new InMemoryConnections(), TransmittableInvoices::invoice());
        $this->transmission($id)->requestSubmission(PlatformConnectionId::fromString('01960000-0000-7000-8000-0000000ec001'), $this->now());
        $submitter = new FakeSubmitter();

        $this->submitHandler($submitter, connections: $connections)(new SubmitTransmission($id));

        self::assertSame(BlockReason::ORGANISATION_MISMATCH->value, $this->buses->commandsOf(RecordTransmissionBlocked::class)[0]->reason);
        self::assertSame([], $submitter->flows);
    }

    public function testAPermanentRefusalBlocksWithoutRetry(): void
    {
        $id = $this->request(InMemoryConnections::verified(TransmittableInvoices::SELLER), TransmittableInvoices::invoice());

        $this->submitHandler(new FakeSubmitter(refuse: true))(new SubmitTransmission($id));

        $blocked = $this->buses->commandsOf(RecordTransmissionBlocked::class)[0];
        self::assertSame(BlockReason::PLATFORM_REFUSED->value, $blocked->reason);
        self::assertSame('Invalid recipient', $blocked->detail);
    }

    public function testATransientFailurePropagatesForMessengerToRetry(): void
    {
        $id = $this->request(InMemoryConnections::verified(TransmittableInvoices::SELLER), TransmittableInvoices::invoice());

        $this->expectException(ExternalServiceUnavailable::class);

        $this->submitHandler(new FakeSubmitter(down: true))(new SubmitTransmission($id));
    }

    public function testNothingIsSentForATransmissionWithoutConnectionOrAlreadySubmitted(): void
    {
        $waiting   = $this->request(new InMemoryConnections(), TransmittableInvoices::invoice());
        $submitter = new FakeSubmitter();
        $handler   = $this->submitHandler($submitter);

        $handler(new SubmitTransmission($waiting));

        $done = $this->request(InMemoryConnections::verified(TransmittableInvoices::SELLER), TransmittableInvoices::invoice('doc-2', 'PAR-2'));
        $this->transmission($done)->markBlocked(BlockReason::RENDERING_FAILED, null, $this->now());
        $handler(new SubmitTransmission($done));

        self::assertSame([], $submitter->flows);
    }

    public function testACreditNoteWaitsForItsInvoiceThenFollowsIt(): void
    {
        $connections = InMemoryConnections::verified(TransmittableInvoices::SELLER);
        $invoice     = $this->transmission($this->request($connections, TransmittableInvoices::invoice()));
        $note        = $this->request($connections, TransmittableInvoices::creditNote());
        $submitter   = new FakeSubmitter();

        $this->submitHandler($submitter, connections: $connections)(new SubmitTransmission($note));
        self::assertSame([], $submitter->flows);

        $invoice->markSubmitting(DocumentReference::of('f', str_repeat('a', 64)), ElectronicAddress::of('0225', 'x'), $this->now());
        $invoice->markSubmitted(FlowId::fromString('flow-inv'), $this->now());

        $this->submitHandler($submitter, connections: $connections)(new SubmitTransmission($note));
        self::assertCount(1, $submitter->flows);
    }

    public function testACreditNoteWhoseInvoiceWasRefusedMeanwhileIsNotRequired(): void
    {
        $connections = InMemoryConnections::verified(TransmittableInvoices::SELLER);
        $invoice     = $this->transmission($this->request($connections, TransmittableInvoices::invoice()));
        $note        = $this->request($connections, TransmittableInvoices::creditNote());
        $invoice->markBlocked(BlockReason::RENDERING_FAILED, null, $this->now());

        $this->submitHandler(connections: $connections)(new SubmitTransmission($note));

        self::assertCount(1, $this->buses->commandsOf(RecordTransmissionNotRequired::class));
    }

    // ── Record ──

    public function testRecordHandlersApplyTheOutcome(): void
    {
        $id = $this->request(InMemoryConnections::verified(TransmittableInvoices::SELLER), TransmittableInvoices::invoice());
        $at = '2026-10-21T10:00:00+00:00';

        new RecordSubmissionPreparedHandler($this->transmissions, $this->buses->publisher(), $this->clock())(new RecordSubmissionPrepared($id, 'stored', str_repeat('d', 64), '0225', '523456788'));
        self::assertSame(TransmissionState::SUBMITTING, $this->transmission($id)->state());

        new RecordSubmissionReceiptHandler($this->transmissions, $this->buses->publisher())(new RecordSubmissionReceipt($id, 'flow-1', $at));
        self::assertSame(TransmissionState::SUBMITTED, $this->transmission($id)->state());

        new RecordLifecycleStatusHandler($this->transmissions, $this->buses->publisher())(new RecordLifecycleStatus($id, 205, null, null, 'cdar-1', $at));
        self::assertSame(LifecycleStatusCode::APPROVED, $this->transmission($id)->currentStatus()?->code);

        $other = $this->request(new InMemoryConnections(), TransmittableInvoices::invoice('doc-2', 'PAR-2'));
        new RecordTransmissionBlockedHandler($this->transmissions, $this->buses->publisher(), $this->clock())(new RecordTransmissionBlocked($other, 'PLATFORM_REFUSED', 'no'));
        self::assertSame(BlockReason::PLATFORM_REFUSED, $this->transmission($other)->blockReason());

        $third = $this->request(new InMemoryConnections(), TransmittableInvoices::invoice('doc-3', 'PAR-3'));
        new RecordTransmissionNotRequiredHandler($this->transmissions, $this->buses->publisher(), $this->clock())(new RecordTransmissionNotRequired($third, 'void'));
        self::assertSame(TransmissionState::NOT_REQUIRED, $this->transmission($third)->state());
    }

    private function request(InMemoryConnections $connections, TransmittableInvoice $invoice): string
    {
        return new RequestInvoiceTransmissionHandler(
            $this->transmissions,
            $connections,
            $this->buses,
            $this->buses->publisher(),
            $this->uuids(),
            $this->clock(),
        )(new RequestInvoiceTransmission('01960000-0000-7000-8000-0000000ee0c1', $invoice));
    }

    private function submitHandler(
        FlowSubmitterInterface $submitter = new FakeSubmitter(),
        DocumentStoreInterface $store = new FakeStore(),
        ?InMemoryConnections $connections = null,
        DirectoryLookupInterface $directory = new FakeDirectory(null),
        InvoiceDocumentRendererInterface $renderer = new FakeRenderer(),
        LoggerInterface $logger = new NullLogger(),
    ): SubmitTransmissionHandler {
        return new SubmitTransmissionHandler(
            $this->transmissions,
            $connections ?? InMemoryConnections::verified(TransmittableInvoices::SELLER),
            $directory,
            $renderer,
            $store,
            $submitter,
            $this->buses,
            $logger,
        );
    }

    private function transmission(string $id): OutboundTransmission
    {
        return $this->transmissions->get(OutboundTransmissionId::fromString($id));
    }

    private function uuids(): UuidGeneratorInterface
    {
        $uuids = $this->createStub(UuidGeneratorInterface::class);
        $uuids->method('generate')->willReturnCallback(fn (): string => \sprintf('01960000-0000-7000-8000-%012d', ++$this->ids));

        return $uuids;
    }

    private function clock(): FrozenClock
    {
        return new FrozenClock($this->now());
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-10-21 10:00:00');
    }
}
