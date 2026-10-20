<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\ElectronicInvoicing\Application;

use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\ValueObject\Siren;
use App\System\ElectronicInvoicing\Application\Command\AdvanceSyncCursor\AdvanceSyncCursor;
use App\System\ElectronicInvoicing\Application\Command\AdvanceSyncCursor\AdvanceSyncCursorHandler;
use App\System\ElectronicInvoicing\Application\Command\OpenPlatformConnection\OpenPlatformConnection;
use App\System\ElectronicInvoicing\Application\Command\OpenPlatformConnection\OpenPlatformConnectionHandler;
use App\System\ElectronicInvoicing\Application\Command\RecordLifecycleStatus\RecordLifecycleStatus;
use App\System\ElectronicInvoicing\Application\Command\RecordUnprocessedFlow\RecordUnprocessedFlow;
use App\System\ElectronicInvoicing\Application\Command\RecordUnprocessedFlow\RecordUnprocessedFlowHandler;
use App\System\ElectronicInvoicing\Application\Command\ReleasePendingTransmissions\ReleasePendingTransmissions;
use App\System\ElectronicInvoicing\Application\Command\ReleasePendingTransmissions\ReleasePendingTransmissionsHandler;
use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\RequestInvoiceTransmission;
use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\RequestInvoiceTransmissionHandler;
use App\System\ElectronicInvoicing\Application\Command\SubmitTransmission\SubmitTransmission;
use App\System\ElectronicInvoicing\Application\Command\SyncAllPlatforms\SyncAllPlatforms;
use App\System\ElectronicInvoicing\Application\Command\SyncAllPlatforms\SyncAllPlatformsHandler;
use App\System\ElectronicInvoicing\Application\Command\SyncPlatformFlows\SyncPlatformFlows;
use App\System\ElectronicInvoicing\Application\Command\SyncPlatformFlows\SyncPlatformFlowsHandler;
use App\System\ElectronicInvoicing\Application\Command\VerifyPlatformConnection\VerifyPlatformConnection;
use App\System\ElectronicInvoicing\Application\Command\VerifyPlatformConnection\VerifyPlatformConnectionHandler;
use App\System\ElectronicInvoicing\Application\EventSubscriber\OnPlatformConnectionActivatedRelease;
use App\System\ElectronicInvoicing\Application\Policy\CreditNoteFollowsItsInvoice;
use App\System\ElectronicInvoicing\Application\Policy\ResolveTransmissionForFlow;
use App\System\ElectronicInvoicing\Application\Port\ConfiguredPlatformInterface;
use App\System\ElectronicInvoicing\Application\Port\ParsedLifecycleMessage;
use App\System\ElectronicInvoicing\Application\Query\GetTransmissionStatus\GetTransmissionStatus;
use App\System\ElectronicInvoicing\Application\Query\GetTransmissionStatus\GetTransmissionStatusHandler;
use App\System\ElectronicInvoicing\Domain\Connection\PlatformConnectionId;
use App\System\ElectronicInvoicing\Domain\Event\PlatformConnectionActivated;
use App\System\ElectronicInvoicing\Domain\Exception\PlatformConnectionNotFoundException;
use App\System\ElectronicInvoicing\Domain\Exception\PlatformConnectionRefusedException;
use App\System\ElectronicInvoicing\Domain\Transmission\OutboundTransmissionId;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\System\ElectronicInvoicing\Support\CapturingBuses;
use App\Tests\Unit\System\ElectronicInvoicing\Support\FakeParser;
use App\Tests\Unit\System\ElectronicInvoicing\Support\FakePlatformFlows;
use App\Tests\Unit\System\ElectronicInvoicing\Support\FakeSchedule;
use App\Tests\Unit\System\ElectronicInvoicing\Support\FakeUnprocessedFlows;
use App\Tests\Unit\System\ElectronicInvoicing\Support\InMemoryConnections;
use App\Tests\Unit\System\ElectronicInvoicing\Support\InMemoryTransmissions;
use App\Tests\Unit\System\ElectronicInvoicing\Support\TransmittableInvoices;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

final class SynchronisationHandlersTest extends TestCase
{
    private InMemoryTransmissions $transmissions;
    private CapturingBuses $buses;
    private int $ids = 0;

    protected function setUp(): void
    {
        $this->transmissions = new InMemoryTransmissions();
        $this->buses         = new CapturingBuses();
    }

    // ── Release ──

    public function testReleasingSendsWhatEachMarkedRowWaitedToSend(): void
    {
        $first                 = $this->request('doc-1', 'PAR-1');
        $second                = $this->request('doc-2', 'PAR-2');
        $third                 = $this->request('doc-3', 'PAR-3');
        $this->buses->external = [];

        $schedule = new FakeSchedule(prepared: [$first, $second, $third], markable: [$first, $third]);
        $sent     = $this->release($schedule, InMemoryConnections::verified(TransmittableInvoices::SELLER));

        self::assertSame(2, $sent);
        self::assertSame([$first, $third], array_map(static fn (array $e): string => $e[0] instanceof SubmitTransmission ? $e[0]->transmissionId : '', $this->buses->external));
        self::assertSame('queued', $this->transmissions->get(OutboundTransmissionId::fromString($first))->viewState());
        self::assertSame('pending_connection', $this->transmissions->get(OutboundTransmissionId::fromString($second))->viewState());
    }

    public function testNothingIsReleasedWithoutAVerifiedConnection(): void
    {
        $id = $this->request('doc-1', 'PAR-1');

        self::assertSame(0, $this->release(new FakeSchedule(prepared: [$id], markable: [$id]), new InMemoryConnections()));
    }

    public function testAnExhaustedTransmissionIsNotSentAgain(): void
    {
        $id           = $this->request('doc-1', 'PAR-1');
        $transmission = $this->transmissions->get(OutboundTransmissionId::fromString($id));

        for ($i = 0; $i <= 10; ++$i) {
            $transmission->requestSubmission(PlatformConnectionId::fromString('01960000-0000-7000-8000-0000000ec001'), new \DateTimeImmutable());
        }

        $this->buses->external = [];

        self::assertSame(0, $this->release(new FakeSchedule(prepared: [$id], markable: [$id]), InMemoryConnections::verified(TransmittableInvoices::SELLER)));
    }

    public function testActivatingAConnectionReleasesItsOrganisation(): void
    {
        new OnPlatformConnectionActivatedRelease($this->buses)(new PlatformConnectionActivated('c', TransmittableInvoices::SELLER, 'now'));

        $release = $this->buses->commandsOf(ReleasePendingTransmissions::class)[0];
        self::assertSame(TransmittableInvoices::SELLER, $release->organisationId);
    }

    // ── Sync ──

    public function testSyncRecordsStatusesAndAdvancesTheCursorToTheLastFlow(): void
    {
        $id          = $this->request('doc-1', 'PAR-1');
        $connections = InMemoryConnections::verified(TransmittableInvoices::SELLER);
        $at          = new \DateTimeImmutable('2026-10-21 10:30');
        $flows       = new FakePlatformFlows([
            ['f-1', $at, '200|PAR-1|812456903'],
            ['f-2', $at, '205|PAR-1|812456903'],
        ]);

        $this->sync($connections, $flows)(new SyncPlatformFlows(TransmittableInvoices::SELLER));

        $statuses = $this->buses->commandsOf(RecordLifecycleStatus::class);
        self::assertSame([200, 205], array_map(static fn (RecordLifecycleStatus $c): int => $c->statusCode, $statuses));
        self::assertSame($id, $statuses[0]->transmissionId);
        self::assertSame('f-1', $statuses[0]->sourceFlowId);

        $cursor = $this->buses->commandsOf(AdvanceSyncCursor::class)[0];
        self::assertSame('f-2', $cursor->lastFlowId);
        self::assertSame('2026-10-21 10:30', new \DateTimeImmutable($cursor->updatedAfter)->format('Y-m-d H:i'));
    }

    public function testFlowsSharingATimestampAreReadPageAfterPageWithoutSkipping(): void
    {
        $this->request('doc-1', 'PAR-1');
        $at    = new \DateTimeImmutable('2026-10-21 10:30');
        $flows = [];

        for ($i = 0; $i < 120; ++$i) {
            $flows[] = [\sprintf('f-%03d', $i), $at, '202|PAR-1|812456903'];
        }

        $platform = new FakePlatformFlows($flows);
        $this->sync(InMemoryConnections::verified(TransmittableInvoices::SELLER), $platform)(new SyncPlatformFlows(TransmittableInvoices::SELLER));

        self::assertCount(120, $this->buses->commandsOf(RecordLifecycleStatus::class));
        self::assertSame(3, $platform->searches);
        self::assertSame('f-119', $this->buses->commandsOf(AdvanceSyncCursor::class)[0]->lastFlowId);
    }

    public function testThePageCapStopsARunWhereItCanResume(): void
    {
        $this->request('doc-1', 'PAR-1');
        $at    = new \DateTimeImmutable('2026-10-21 10:30');
        $flows = [];

        for ($i = 0; $i < 600; ++$i) {
            $flows[] = [\sprintf('f-%03d', $i), $at, '202|PAR-1|812456903'];
        }

        $this->sync(InMemoryConnections::verified(TransmittableInvoices::SELLER), new FakePlatformFlows($flows))(new SyncPlatformFlows(TransmittableInvoices::SELLER));

        self::assertSame('f-499', $this->buses->commandsOf(AdvanceSyncCursor::class)[0]->lastFlowId);
    }

    /**
     * F7: a flow that cannot be fetched or read is quarantined — content and
     * error kept — and the others of the organisation go on; the cursor moves.
     */
    public function testUnreadableAndUnresolvedFlowsAreQuarantinedThenRetried(): void
    {
        $this->request('doc-1', 'PAR-1');
        $at          = new \DateTimeImmutable('2026-10-21 10:30');
        $unprocessed = new FakeUnprocessedFlows();
        $flows       = new FakePlatformFlows([
            ['f-0', $at, 'down'],
            ['f-1', $at, 'garbage'],
            ['f-2', $at, '200|PAR-404|812456903'],
            ['f-3', $at, '999|PAR-1|812456903'],
            ['f-4', $at, '200|PAR-1|523456788'],
            ['f-5', $at, '202|PAR-1|812456903'],
        ]);

        $this->sync(InMemoryConnections::verified(TransmittableInvoices::SELLER), $flows, $unprocessed)(new SyncPlatformFlows(TransmittableInvoices::SELLER));

        $kept = $this->buses->commandsOf(RecordUnprocessedFlow::class);
        self::assertSame(['f-0', 'f-1', 'f-2', 'f-3', 'f-4'], array_map(static fn (RecordUnprocessedFlow $c): string => $c->flowId, $kept));
        self::assertSame([RecordUnprocessedFlow::UNFETCHABLE, null], [$kept[0]->reason, $kept[0]->content]);
        self::assertStringContainsString('503', (string) $kept[0]->error);
        self::assertSame([RecordUnprocessedFlow::UNPARSABLE, 'garbage'], [$kept[1]->reason, $kept[1]->content]);
        self::assertStringContainsString('not a test message', (string) $kept[1]->error);
        self::assertSame([RecordUnprocessedFlow::UNRESOLVED, '200|PAR-404|812456903'], [$kept[2]->reason, $kept[2]->content]);
        self::assertSame(['f-5'], array_map(static fn (RecordLifecycleStatus $c): string => $c->sourceFlowId, $this->buses->commandsOf(RecordLifecycleStatus::class)));
        self::assertSame('f-5', $this->buses->commandsOf(AdvanceSyncCursor::class)[0]->lastFlowId);

        // Next run: f-2's document now exists, it is resolved first.
        $this->request('doc-2', 'PAR-404');
        $unprocessed->attempts = ['f-2' => 1];
        $this->buses->commands = [];
        $this->sync(InMemoryConnections::verified(TransmittableInvoices::SELLER), $flows, $unprocessed)(new SyncPlatformFlows(TransmittableInvoices::SELLER));

        self::assertNull($this->buses->commandsOf(RecordUnprocessedFlow::class)[0]->reason);
    }

    public function testAnOrganisationWithoutPolledConnectionIsNotSynchronised(): void
    {
        $platform = new FakePlatformFlows();

        $this->sync(new InMemoryConnections(), $platform)(new SyncPlatformFlows(TransmittableInvoices::SELLER));

        self::assertSame(0, $platform->searches);
        self::assertSame([], $this->buses->commands);
    }

    public function testAnEmptyRunLeavesTheCursorAlone(): void
    {
        $this->sync(InMemoryConnections::verified(TransmittableInvoices::SELLER), new FakePlatformFlows())(new SyncPlatformFlows(TransmittableInvoices::SELLER));

        self::assertSame([], $this->buses->commandsOf(AdvanceSyncCursor::class));
    }

    public function testTheResolverIgnoresAnotherSeller(): void
    {
        $resolver = new ResolveTransmissionForFlow($this->transmissions);
        $this->request('doc-1', 'PAR-1');

        $message = new ParsedLifecycleMessage(200, null, null, 'PAR-1', null, null, null, new \DateTimeImmutable());
        self::assertNotNull($resolver->resolve(TransmittableInvoices::SELLER, $message));

        $other = new ParsedLifecycleMessage(200, null, null, 'PAR-1', null, '380', '523456788', new \DateTimeImmutable());
        self::assertNull($resolver->resolve(TransmittableInvoices::SELLER, $other));
    }

    public function testEveryQuarantineIsLoggedAsAnError(): void
    {
        $unprocessed = new FakeUnprocessedFlows();
        $logger      = $this->createMock(LoggerInterface::class);
        $messages    = [];
        $logger->expects(self::exactly(RecordUnprocessedFlowHandler::MAX_ATTEMPTS))->method('error')->willReturnCallback(static function (string $message) use (&$messages): void {
            $messages[] = $message;
        });
        $handler = new RecordUnprocessedFlowHandler($unprocessed, $this->clock(), $logger);

        for ($i = 0; $i < RecordUnprocessedFlowHandler::MAX_ATTEMPTS; ++$i) {
            $handler(new RecordUnprocessedFlow('812456903', 'f-1', RecordUnprocessedFlow::UNPARSABLE, 'malformed XML.', '<broken'));
        }

        self::assertSame(['reason' => 'unparsable', 'error' => 'malformed XML.', 'content' => '<broken'], $unprocessed->quarantined['f-1']);
        self::assertStringContainsString('quarantined', $messages[0]);
        self::assertStringContainsString('no longer retried', (string) array_last($messages));

        $handler(new RecordUnprocessedFlow('812456903', 'f-1', null));
        self::assertSame([], $unprocessed->attempts);
    }

    public function testTheCursorAdvancesThroughItsCommand(): void
    {
        $connections = InMemoryConnections::verified(TransmittableInvoices::SELLER);
        $handler     = new AdvanceSyncCursorHandler($connections);

        $handler(new AdvanceSyncCursor(TransmittableInvoices::SELLER, '2026-10-22T08:00:00+00:00', 'f-9'));
        $handler(new AdvanceSyncCursor('523456788', '2026-10-22T08:00:00+00:00', 'f-9'));

        self::assertSame('f-9', $connections->byOrganisation(Siren::fromInput(TransmittableInvoices::SELLER))?->cursor()->lastFlowId());
    }

    // ── Periodic tick ──

    public function testTheTickSynchronisesResendsAndReleases(): void
    {
        $connections = InMemoryConnections::verified(TransmittableInvoices::SELLER);
        $waiting     = $this->request('doc-1', 'PAR-1');
        $this->transmissions->get(OutboundTransmissionId::fromString($waiting))->requestSubmission(PlatformConnectionId::fromString('01960000-0000-7000-8000-0000000ec001'), new \DateTimeImmutable());
        $orphan                = $this->request('doc-2', 'PAR-2');
        $this->buses->external = [];

        new SyncAllPlatformsHandler(
            $connections,
            new FakeSchedule(stale: [$waiting, $orphan], waiting: [TransmittableInvoices::SELLER, '523456788'], markable: [$waiting, $orphan]),
            $this->transmissions,
            $this->buses,
            $this->buses,
            $this->buses->publisher(),
            $this->clock(),
        )(new SyncAllPlatforms());

        /** @var list<array{object, list<object>}> $sent */
        $sent            = $this->buses->external;
        [$sync, $stamps] = $sent[0];
        self::assertInstanceOf(SyncPlatformFlows::class, $sync);
        self::assertInstanceOf(TransportNamesStamp::class, $stamps[0]);
        self::assertSame(['async_external_batch'], $stamps[0]->getTransportNames());

        self::assertInstanceOf(SubmitTransmission::class, $this->buses->external[1][0]);
        self::assertSame($waiting, $this->buses->external[1][0]->transmissionId);
        self::assertCount(2, $this->buses->external);
        self::assertSame([TransmittableInvoices::SELLER], array_map(static fn (ReleasePendingTransmissions $c): string => $c->organisationId, $this->buses->commandsOf(ReleasePendingTransmissions::class)));
    }

    // ── Connection ──

    public function testAConnectionIsOpenedOncePerOrganisationThenVerified(): void
    {
        $connections = new InMemoryConnections();
        $id          = $this->open($connections, 'simulator')(new OpenPlatformConnection('812456903'));

        self::assertSame('pending', $connections->get(PlatformConnectionId::fromString($id))->state()->value);

        new VerifyPlatformConnectionHandler($connections, $this->buses->publisher(), $this->clock())(new VerifyPlatformConnection('812456903'));
        self::assertTrue($connections->get(PlatformConnectionId::fromString($id))->acceptsSubmissions());
        self::assertInstanceOf(PlatformConnectionActivated::class, $this->buses->events[0]);

        $this->expectException(PlatformConnectionRefusedException::class);
        $this->open($connections, 'simulator')(new OpenPlatformConnection('812456903'));
    }

    public function testNoConnectionWhenNoPlatformIsConfigured(): void
    {
        try {
            $this->open(new InMemoryConnections(), null)(new OpenPlatformConnection('812456903'));
            self::fail('Expected a refusal.');
        } catch (PlatformConnectionRefusedException $e) {
            self::assertSame(PlatformConnectionRefusedException::NO_PLATFORM, $e->reason);
            self::assertSame('Aucune plateforme de facturation électronique n\'est configurée.', $e->getMessage());
        }
    }

    public function testVerifyingAnUnknownOrganisationFails(): void
    {
        $this->expectException(PlatformConnectionNotFoundException::class);

        new VerifyPlatformConnectionHandler(new InMemoryConnections(), $this->buses->publisher(), $this->clock())(new VerifyPlatformConnection('812456903'));
    }

    public function testTheStatusQueryReturnsTheCurrentView(): void
    {
        $this->request('doc-1', 'PAR-1');
        $handler = new GetTransmissionStatusHandler($this->transmissions);

        $view = $handler(new GetTransmissionStatus('doc-1'));
        self::assertNotNull($view);
        self::assertSame('pending_connection', $view->state);
        self::assertSame(1, $view->sequence);
        self::assertNull($view->statusCode);
        self::assertNull($view->electronicAddress);
        self::assertNull($handler(new GetTransmissionStatus('unknown')));
    }

    private function request(string $documentId, string $number): string
    {
        return new RequestInvoiceTransmissionHandler(
            $this->transmissions,
            new InMemoryConnections(),
            $this->buses,
            $this->buses->publisher(),
            new CreditNoteFollowsItsInvoice($this->transmissions),
            $this->uuids(),
            $this->clock(),
        )(new RequestInvoiceTransmission('01960000-0000-7000-8000-0000000ee0c1', TransmittableInvoices::invoice($documentId, $number)));
    }

    private function release(FakeSchedule $schedule, InMemoryConnections $connections): int
    {
        return new ReleasePendingTransmissionsHandler($schedule, $connections, $this->transmissions, $this->buses, $this->buses->publisher(), $this->clock())(new ReleasePendingTransmissions(TransmittableInvoices::SELLER));
    }

    private function sync(InMemoryConnections $connections, FakePlatformFlows $flows, ?FakeUnprocessedFlows $unprocessed = null): SyncPlatformFlowsHandler
    {
        return new SyncPlatformFlowsHandler(
            $connections,
            $flows,
            $flows,
            new FakeParser(),
            new ResolveTransmissionForFlow($this->transmissions),
            $unprocessed ?? new FakeUnprocessedFlows(),
            $this->buses,
        );
    }

    private function open(InMemoryConnections $connections, ?string $platform): OpenPlatformConnectionHandler
    {
        $configured = $this->createStub(ConfiguredPlatformInterface::class);
        $configured->method('code')->willReturn($platform);

        return new OpenPlatformConnectionHandler($connections, $configured, $this->uuids(), $this->clock());
    }

    private function uuids(): UuidGeneratorInterface
    {
        $uuids = $this->createStub(UuidGeneratorInterface::class);
        $uuids->method('generate')->willReturnCallback(fn (): string => \sprintf('01960000-0000-7000-8000-%012d', ++$this->ids));

        return $uuids;
    }

    private function clock(): FrozenClock
    {
        return new FrozenClock(new \DateTimeImmutable('2026-10-21 10:00:00'));
    }
}
