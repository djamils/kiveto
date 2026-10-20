<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\ElectronicInvoicing;

use App\Shared\Domain\ValueObject\Siren;
use App\System\ElectronicInvoicing\Application\Port\DocumentStoreInterface;
use App\System\ElectronicInvoicing\Application\Port\SubmissionScheduleInterface;
use App\System\ElectronicInvoicing\Application\Port\UnprocessedFlowsInterface;
use App\System\ElectronicInvoicing\Domain\Connection\PlatformCode;
use App\System\ElectronicInvoicing\Domain\Connection\PlatformConnection;
use App\System\ElectronicInvoicing\Domain\Connection\PlatformConnectionId;
use App\System\ElectronicInvoicing\Domain\Connection\PlatformConnectionRepositoryInterface;
use App\System\ElectronicInvoicing\Domain\Connection\SyncCursor;
use App\System\ElectronicInvoicing\Domain\Exception\PlatformConnectionNotFoundException;
use App\System\ElectronicInvoicing\Domain\Exception\TransmissionNotFoundException;
use App\System\ElectronicInvoicing\Domain\Transmission\BlockReason;
use App\System\ElectronicInvoicing\Domain\Transmission\DocumentReference;
use App\System\ElectronicInvoicing\Domain\Transmission\ElectronicAddress;
use App\System\ElectronicInvoicing\Domain\Transmission\FlowId;
use App\System\ElectronicInvoicing\Domain\Transmission\LifecycleStatusCode;
use App\System\ElectronicInvoicing\Domain\Transmission\OutboundTransmission;
use App\System\ElectronicInvoicing\Domain\Transmission\OutboundTransmissionId;
use App\System\ElectronicInvoicing\Domain\Transmission\OutboundTransmissionRepositoryInterface;
use App\System\ElectronicInvoicing\Domain\Transmission\TrackingId;
use App\System\ElectronicInvoicing\Domain\Transmission\TransmissionKind;
use App\System\ElectronicInvoicing\Domain\Transmission\TransmissionState;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PersistenceTest extends KernelTestCase
{
    private const string CONNECTION = '01960000-0000-7000-8000-0000000ec0a1';

    public function testAConnectionComesBackAsItWentIn(): void
    {
        $connections = $this->service(PlatformConnectionRepositoryInterface::class);
        $connection  = PlatformConnection::open(PlatformConnectionId::fromString(self::CONNECTION), Siren::fromInput('812456903'), PlatformCode::fromString('simulator'), new \DateTimeImmutable('2026-10-21 10:00:00'));
        $connections->save($connection);

        $connection->markVerified(new \DateTimeImmutable('2026-10-21 10:05:00'));
        $connection->advanceCursor(SyncCursor::at(new \DateTimeImmutable('2026-10-21 10:30:00'), 'flow-7'));
        $connections->save($connection);

        $loaded = $connections->byOrganisation(Siren::fromInput('812456903'));
        self::assertNotNull($loaded);
        self::assertTrue($loaded->isPolled());
        self::assertSame('simulator', $loaded->platform()->toString());
        self::assertSame('flow-7', $loaded->cursor()->lastFlowId());
        self::assertSame('2026-10-21 10:30:00', $loaded->cursor()->updatedAfter()->format('Y-m-d H:i:s'));
        self::assertSame('2026-10-21 10:05:00', $loaded->verifiedAt()?->format('Y-m-d H:i:s'));
        self::assertSame('2026-10-21 10:00:00', $loaded->openedAt()->format('Y-m-d H:i:s'));
        self::assertSame(self::CONNECTION, $connections->get(PlatformConnectionId::fromString(self::CONNECTION))->id()->toString());
        self::assertCount(1, $connections->polled());
        self::assertNull($connections->byOrganisation(Siren::fromInput('523456788')));
    }

    public function testAnUnknownConnectionIsNotFound(): void
    {
        $this->expectException(PlatformConnectionNotFoundException::class);

        $this->service(PlatformConnectionRepositoryInterface::class)->get(PlatformConnectionId::fromString(self::CONNECTION));
    }

    public function testATransmissionAndItsLifecycleComeBack(): void
    {
        $transmissions = $this->service(OutboundTransmissionRepositoryInterface::class);
        $transmission  = $this->prepared('01960000-0000-7000-8000-0000000ee1a1', 'doc-1', 'PAR-1');
        $transmissions->save($transmission);

        $transmission->requestSubmission(PlatformConnectionId::fromString(self::CONNECTION), new \DateTimeImmutable('2026-10-21 10:01:00'));
        $transmission->markSubmitting(DocumentReference::of('2026/10/21/doc-1.xml', str_repeat('e', 64)), ElectronicAddress::of('0225', '523456788'), new \DateTimeImmutable());
        $transmission->markSubmitted(FlowId::fromString('flow-1'), new \DateTimeImmutable('2026-10-21 10:02:00'));
        $transmission->recordLifecycleStatus(LifecycleStatusCode::DEPOSITED, null, null, FlowId::fromString('cdar-1'), new \DateTimeImmutable('2026-10-21 10:03:00'));
        $transmissions->save($transmission);
        $transmission->recordLifecycleStatus(LifecycleStatusCode::DISPUTED, 'TX_TVA_ERR', str_repeat('é', 600), FlowId::fromString('cdar-2'), new \DateTimeImmutable('2026-10-21 10:04:00'));
        $transmissions->save($transmission);

        $loaded = $transmissions->byTrackingId(TrackingId::fromString('doc-1'));
        self::assertNotNull($loaded);
        self::assertSame(TransmissionState::ACCEPTED, $loaded->state());
        self::assertSame('flow-1', $loaded->flowId()?->toString());
        self::assertSame(str_repeat('e', 64), $loaded->document()?->sha256());
        self::assertSame('523456788', $loaded->addressUsed()?->identifier());
        self::assertSame(self::CONNECTION, $loaded->connectionId()?->toString());
        self::assertSame(1, $loaded->submissionAttempts());
        self::assertSame($transmission->version(), $loaded->version());
        self::assertCount(2, $loaded->lifecycle());
        $current = $loaded->currentStatus();
        self::assertNotNull($current);
        self::assertSame(LifecycleStatusCode::DISPUTED, $current->code);
        self::assertSame(512, mb_strlen((string) $current->reasonText));
        self::assertSame(['number' => 'PAR-1'], $loaded->payload());
        self::assertSame('523456788', $loaded->buyerSiren()?->value());
        self::assertSame($loaded->id()->toString(), $transmissions->byFlowId(FlowId::fromString('flow-1'))?->id()->toString());
        self::assertNotNull($transmissions->byDocument(Siren::fromInput('812456903'), TransmissionKind::INVOICE, 'PAR-1'));
        self::assertNull($transmissions->byDocument(Siren::fromInput('812456903'), TransmissionKind::CREDIT_NOTE, 'PAR-1'));
    }

    public function testABlockedTransmissionKeepsItsReason(): void
    {
        $transmissions = $this->service(OutboundTransmissionRepositoryInterface::class);
        $transmission  = $this->prepared('01960000-0000-7000-8000-0000000ee1a2', 'doc-2', 'PAR-2', null);
        $transmission->markBlocked(BlockReason::ORGANISATION_MISMATCH, 'wrong SIREN', new \DateTimeImmutable());
        $transmissions->save($transmission);

        $loaded = $transmissions->get($transmission->id());
        self::assertSame(BlockReason::ORGANISATION_MISMATCH, $loaded->blockReason());
        self::assertSame('wrong SIREN', $loaded->detail());
        self::assertNull($loaded->buyerSiren());
    }

    public function testAnUnknownTransmissionIsNotFound(): void
    {
        $this->expectException(TransmissionNotFoundException::class);

        $this->service(OutboundTransmissionRepositoryInterface::class)->get(OutboundTransmissionId::fromString('01960000-0000-7000-8000-0000000ee1ff'));
    }

    public function testEachRowIsMarkedOncePerWindow(): void
    {
        $transmissions = $this->service(OutboundTransmissionRepositoryInterface::class);
        $schedule      = $this->service(SubmissionScheduleInterface::class);
        $note          = $this->prepared('01960000-0000-7000-8000-0000000ee1b1', 'cn-1', 'AV-1', '523456788', TransmissionKind::CREDIT_NOTE);
        $invoice       = $this->prepared('01960000-0000-7000-8000-0000000ee1b2', 'doc-3', 'PAR-3');
        $transmissions->save($note);
        $transmissions->save($invoice);

        $waiting = $schedule->preparedWithoutConnection('812456903');
        self::assertSame([$invoice->id()->toString(), $note->id()->toString()], $waiting);
        self::assertSame(['812456903'], $schedule->organisationsWaitingForConnection());

        $now    = new \DateTimeImmutable('2026-10-21 12:00:00');
        $before = $now->modify('-15 minutes');
        self::assertSame($waiting, $schedule->markSubmissionRequested($waiting, $now, $before));
        // A concurrent release marks nothing more.
        self::assertSame([], $schedule->markSubmissionRequested($waiting, $now, $before));

        $loaded = $transmissions->get($invoice->id());
        self::assertSame(1, $loaded->submissionAttempts());
        self::assertSame($invoice->version() + 1, $loaded->version());

        $loaded->acknowledgeSubmissionRequested(PlatformConnectionId::fromString(self::CONNECTION), $now);
        $transmissions->save($loaded);
        self::assertSame([], $schedule->staleRequested($before));
        self::assertSame([$invoice->id()->toString()], $schedule->staleRequested($now->modify('+1 minute')));
    }

    public function testUnprocessedFlowsAreCountedAndResolved(): void
    {
        $flows = $this->service(UnprocessedFlowsInterface::class);
        $now   = new \DateTimeImmutable('2026-10-21 12:00:00');

        self::assertSame(1, $flows->record('812456903', 'f-1', 'unparsable', $now));
        self::assertSame(2, $flows->record('812456903', 'f-1', 'unresolved', $now));
        self::assertSame(1, $flows->record('812456903', 'f-2', 'unparsable', $now));
        self::assertSame(['f-1', 'f-2'], $flows->toRetry('812456903', 5));
        self::assertSame(['f-2'], $flows->toRetry('812456903', 2));

        $flows->resolve('812456903', 'f-1');
        self::assertSame(['f-2'], $flows->toRetry('812456903', 5));
    }

    public function testTheTransmittedFileIsKept(): void
    {
        $store     = $this->service(DocumentStoreInterface::class);
        $reference = $store->store('doc/9', hash('sha256', '<x/>'), '<x/>');

        self::assertStringContainsString('electronic-invoicing/doc_9-', $reference->storageId());
        self::assertSame('<x/>', $store->load($reference));
    }

    private function prepared(string $id, string $tracking, string $number, ?string $buyer = '523456788', TransmissionKind $kind = TransmissionKind::INVOICE): OutboundTransmission
    {
        return OutboundTransmission::prepare(
            id: OutboundTransmissionId::fromString($id),
            clinicId: '01960000-0000-7000-8000-0000000ee0c1',
            kind: $kind,
            trackingId: TrackingId::fromString($tracking),
            documentNumber: $number,
            documentDate: new \DateTimeImmutable('2026-10-21'),
            sellerSiren: Siren::fromInput('812456903'),
            buyerSiren: null !== $buyer ? Siren::fromInput($buyer) : null,
            precedingDocumentNumber: null,
            payload: ['number' => $number],
            now: new \DateTimeImmutable('2026-10-21 10:00:00'),
        );
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(string $class): object
    {
        $service = self::getContainer()->get('test.' . $class);
        \assert($service instanceof $class);

        return $service;
    }
}
