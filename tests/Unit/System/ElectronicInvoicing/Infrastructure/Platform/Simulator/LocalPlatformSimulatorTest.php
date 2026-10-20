<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\ElectronicInvoicing\Infrastructure\Platform\Simulator;

use App\System\ElectronicInvoicing\Domain\Port\FlowSummary;
use App\System\ElectronicInvoicing\Infrastructure\Format\Cdar\CdarParser;
use App\System\ElectronicInvoicing\Infrastructure\Platform\Simulator\AdvanceSimulatedFlow;
use App\System\ElectronicInvoicing\Infrastructure\Platform\Simulator\SimulatedFlow;
use App\System\ElectronicInvoicing\Infrastructure\Platform\Simulator\SimulationScenario;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Stamp\DelayStamp;

final class LocalPlatformSimulatorTest extends TestCase
{
    private InMemorySimulatedFlowStore $store;
    private SettableClock $clock;
    private CapturingMessageBus $bus;

    protected function setUp(): void
    {
        $this->store = new InMemorySimulatedFlowStore();
        $this->clock = new SettableClock();
        $this->bus   = new CapturingMessageBus();
    }

    public function testSubmitDepositsTheFileAndEmitsDeposited(): void
    {
        $content = SimulatorKit::cii();
        $receipt = SimulatorKit::simulator($this->store, $this->clock, $this->bus)->submit(SimulatorKit::flow($content));

        self::assertSame('0193a000-0000-7000-8000-000000000001', $receipt->flowId);
        self::assertSame('2026-10-21T10:00:00+00:00', $receipt->submittedAt->format(\DATE_ATOM));

        $flow = $this->store->find($receipt->flowId);
        self::assertNotNull($flow);
        self::assertSame(SimulationScenario::NOMINAL, $flow->scenario);
        self::assertSame('PAR-2026-000001', $flow->documentNumber);
        self::assertSame('380', $flow->typeCode);
        self::assertSame(SimulatorKit::SELLER, $flow->sellerSiren);
        self::assertSame('523456788', $flow->buyerSiren);
        self::assertSame('2026-10-21', $flow->issueDate?->format('Y-m-d'));
        self::assertSame($content, $flow->file);
        self::assertSame([200], array_map(static fn ($s): int => $s->code, $flow->statuses()));

        self::assertCount(1, $this->bus->envelopes);
        $message = $this->bus->envelopes[0]->getMessage();
        self::assertInstanceOf(AdvanceSimulatedFlow::class, $message);
        self::assertSame($receipt->flowId, $message->flowId);
        self::assertInstanceOf(DelayStamp::class, $this->bus->envelopes[0]->last(DelayStamp::class));
    }

    public function testSubmitIsIdempotentOnTrackingIdAndSha256(): void
    {
        $simulator = SimulatorKit::simulator($this->store, $this->clock, $this->bus);
        $flow      = SimulatorKit::flow(SimulatorKit::cii());

        $first = $simulator->submit($flow);
        $this->clock->advance('+1 hour');
        $again = $simulator->submit($flow);

        self::assertSame($first->flowId, $again->flowId);
        self::assertSame($first->submittedAt->getTimestamp(), $again->submittedAt->getTimestamp());
        self::assertSame(1, $this->store->count());
        self::assertCount(1, $this->bus->envelopes);

        $changed = $simulator->submit(SimulatorKit::flow(SimulatorKit::cii(number: 'PAR-2026-000002')));
        self::assertNotSame($first->flowId, $changed->flowId);
    }

    public function testInvalidFileIsInadmissibleWhateverTheBuyer(): void
    {
        $content = str_replace('<ram:TypeCode>380</ram:TypeCode>', '<ram:TypeCode>380</ram:TypeCode><ram:Unexpected/>', SimulatorKit::cii());
        $receipt = SimulatorKit::simulator($this->store, $this->clock, $this->bus)->submit(SimulatorKit::flow($content));

        $flow = $this->store->find($receipt->flowId);
        self::assertNotNull($flow);
        self::assertSame(SimulationScenario::INADMISSIBLE, $flow->scenario);
        self::assertSame(501, $flow->statuses()[0]->code);
        self::assertSame('IRR_SYNTAX', $flow->statuses()[0]->reason);
        self::assertFalse($flow->isPending());
        self::assertSame([], $this->bus->envelopes);
    }

    public function testUnreadableFileFallsBackToTheFlowIdentifiers(): void
    {
        $receipt = SimulatorKit::simulator($this->store, $this->clock)->submit(SimulatorKit::flow('not xml', 'tracking-x'));

        $flow = $this->store->find($receipt->flowId);
        self::assertNotNull($flow);
        self::assertSame('tracking-x', $flow->documentNumber);
        self::assertSame('380', $flow->typeCode);
        self::assertSame(SimulatorKit::SELLER, $flow->sellerSiren);
        self::assertNull($flow->buyerSiren);
        self::assertNull($flow->issueDate);
    }

    /**
     * @param list<array{int, ?string}> $expected
     */
    #[DataProvider('provideScenarioReachesThePlatformThroughTheParserCases')]
    public function testScenarioReachesThePlatformThroughTheParser(string $buyerSiren, array $expected): void
    {
        $simulator = SimulatorKit::simulator($this->store, $this->clock);
        $receipt   = $simulator->submit(SimulatorKit::flow(SimulatorKit::cii($buyerSiren)));
        $flow      = $this->store->find($receipt->flowId);
        self::assertNotNull($flow);

        while ($flow->advance($this->clock->now())) {
            $this->store->save($flow);
        }

        $parser = new CdarParser();
        $read   = [];
        $page   = $simulator->search(SimulatorKit::SELLER, new \DateTimeImmutable('2000-01-01'), '', 50);
        foreach ($page->flows as $summary) {
            $content = $simulator->fetch(SimulatorKit::SELLER, $summary->flowId);
            self::assertSame($summary->flowId, $content->flowId);
            $message = $parser->parse($content->content)[0];
            self::assertSame('PAR-2026-000001', $message->invoiceNumber);
            self::assertSame(SimulatorKit::SELLER, $message->sellerSiren);
            self::assertSame('380', $message->typeCode);
            self::assertSame($summary->updatedAt->getTimestamp(), $message->issuedAt->getTimestamp());
            $read[] = [$message->statusCode, $message->reasonCode];
        }

        self::assertSame($expected, $read);
        self::assertFalse($page->hasMore);
    }

    /**
     * @return iterable<string, array{string, list<array{int, ?string}>}>
     */
    public static function provideScenarioReachesThePlatformThroughTheParserCases(): iterable
    {
        yield 'nominal' => ['523456788', [[200, null], [202, null], [203, null], [205, null]]];
        yield 'rejected' => ['000002139', [[200, null], [213, 'REJ_SEMAN']]];
        yield 'refused' => ['000002105', [[200, null], [202, null], [203, null], [210, 'DOUBLON']]];
        yield 'disputed' => ['000002071', [[200, null], [202, null], [203, null], [207, 'TX_TVA_ERR']]];
        yield 'inadmissible' => ['000005017', [[501, 'IRR_SYNTAX']]];
        yield 'not transmitted' => ['000000000', [[200, 'NON_TRANSMISE']]];
    }

    public function testCreditNoteKeepsItsTypeCode(): void
    {
        $simulator = SimulatorKit::simulator($this->store, $this->clock);
        $receipt   = $simulator->submit(SimulatorKit::flow(SimulatorKit::cii(number: 'AVPAR-2026-000001', creditNote: true)));

        $message = new CdarParser()->parse($simulator->fetch(SimulatorKit::SELLER, $receipt->flowId . '-01')->content)[0];

        self::assertSame('381', $message->typeCode);
        self::assertSame('AVPAR-2026-000001', $message->invoiceNumber);
    }

    /**
     * AC 30b style: 120 flows sharing one timestamp, read page after page
     * with the (updatedAt, flowId) cursor: each exactly once, in order.
     */
    public function testSearchPagesThroughFlowsSharingOneTimestamp(): void
    {
        $simulator = SimulatorKit::simulator($this->store, $this->clock);
        for ($i = 1; $i <= 120; ++$i) {
            $simulator->submit(SimulatorKit::flow('<flow>' . $i . '</flow>', 'tracking-' . $i));
        }
        $simulator->submit(SimulatorKit::flow('<other/>', 'tracking-other', '999999999'));

        $after = new \DateTimeImmutable('2000-01-01');
        $last  = '';
        $seen  = [];
        $pages = 0;
        do {
            $page = $simulator->search(SimulatorKit::SELLER, $after, $last, 50);
            ++$pages;
            foreach ($page->flows as $flow) {
                $seen[] = $flow->flowId;
                $after  = $flow->updatedAt;
                $last   = $flow->flowId;
            }
        } while ($page->hasMore);

        self::assertSame(3, $pages);
        self::assertCount(120, $seen);
        self::assertSame($seen, array_values(array_unique($seen)));
        $sorted = $seen;
        sort($sorted);
        self::assertSame($sorted, $seen);

        $replay = $simulator->search(SimulatorKit::SELLER, $after, $last, 50);
        self::assertSame([], $replay->flows);
        self::assertFalse($replay->hasMore);
    }

    public function testSearchOrdersByTimeThenFlowId(): void
    {
        $simulator = SimulatorKit::simulator($this->store, $this->clock);
        $first     = $simulator->submit(SimulatorKit::flow(SimulatorKit::cii(), 'tracking-1'))->flowId;
        $second    = $simulator->submit(SimulatorKit::flow(SimulatorKit::cii(number: 'PAR-2026-000002'), 'tracking-2'))->flowId;

        $this->clock->advance('+30 seconds');
        $flow = $this->store->find($first);
        self::assertNotNull($flow);
        $flow->advance($this->clock->now());
        $this->store->save($flow);

        $ids = array_map(
            static fn (FlowSummary $s): string => $s->flowId,
            $simulator->search(SimulatorKit::SELLER, new \DateTimeImmutable('2000-01-01'), '', 10)->flows,
        );

        self::assertSame([$first . '-01', $second . '-01', $first . '-02'], $ids);

        $after = $simulator->search(SimulatorKit::SELLER, new \DateTimeImmutable('2026-10-21T10:00:00+00:00'), $first . '-01', 1);
        self::assertSame([$second . '-01'], array_map(static fn (FlowSummary $s): string => $s->flowId, $after->flows));
        self::assertTrue($after->hasMore);
    }

    public function testFetchRefusesUnknownFlowsAndOtherOrganisations(): void
    {
        $simulator = SimulatorKit::simulator($this->store, $this->clock);
        $flowId    = $simulator->submit(SimulatorKit::flow(SimulatorKit::cii()))->flowId;

        foreach ([[SimulatorKit::SELLER, 'nope'], [SimulatorKit::SELLER, $flowId . '-02'], ['999999999', $flowId . '-01']] as [$organisation, $id]) {
            try {
                $simulator->fetch($organisation, $id);
                self::fail('Expected an unknown flow for ' . $id);
            } catch (\RuntimeException $e) {
                self::assertStringContainsString('Unknown flow', $e->getMessage());
            }
        }
    }

    public function testLookupAnswersEveryBuyerButTheUnreachableOne(): void
    {
        $simulator = SimulatorKit::simulator($this->store, $this->clock);

        $entry = $simulator->lookup('523456788');
        self::assertTrue($entry->reachable);
        self::assertSame('0225', $entry->scheme);
        self::assertSame('523456788', $entry->identifier);

        $unreachable = $simulator->lookup('000000000');
        self::assertFalse($unreachable->reachable);
        self::assertNull($unreachable->scheme);
        self::assertNull($unreachable->identifier);
    }

    public function testDepositFlowIdOfAStatusFlow(): void
    {
        self::assertSame('abc-def', SimulatedFlow::depositFlowId('abc-def-03'));
        self::assertSame('plain', SimulatedFlow::depositFlowId('plain'));
    }
}
