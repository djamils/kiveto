<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\ElectronicInvoicing;

use App\System\ElectronicInvoicing\Application\Port\LifecycleMessageParserInterface;
use App\System\ElectronicInvoicing\Domain\Port\FlowFetcherInterface;
use App\System\ElectronicInvoicing\Domain\Port\FlowSearcherInterface;
use App\System\ElectronicInvoicing\Domain\Port\FlowSubmitterInterface;
use App\System\ElectronicInvoicing\Domain\Port\OutboundFlow;
use App\System\ElectronicInvoicing\Infrastructure\Platform\Simulator\SimulatorDriver;
use App\Tests\Unit\System\ElectronicInvoicing\Infrastructure\Platform\Simulator\SimulatorKit;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The contract every approved-platform adapter honours, run against the
 * configured platform (the local simulator in the test environment; T4 adds
 * the real adapter): idempotent submission, (updatedAt, flowId) cursor order
 * and fetchable lifecycle messages Kiveto can parse.
 */
final class PlatformPortsContractTest extends KernelTestCase
{
    private const string ORGANISATION = SimulatorKit::SELLER;

    public function testSubmitIsIdempotentOnTrackingIdAndSha256(): void
    {
        $flow = $this->flow(SimulatorKit::cii(), 'contract-tracking-1');

        $first = $this->submitter()->submit($flow);
        $again = $this->submitter()->submit($flow);

        self::assertSame($first->flowId, $again->flowId);
        self::assertNotSame($first->flowId, $this->submitter()->submit($this->flow(SimulatorKit::cii(number: 'PAR-2026-000002'), 'contract-tracking-1'))->flowId);
    }

    public function testSearchFollowsTheCursorAndFetchReturnsParsableMessages(): void
    {
        $this->submitter()->submit($this->flow(SimulatorKit::cii(), 'contract-tracking-1'));
        $this->submitter()->submit($this->flow(SimulatorKit::cii('000002105', 'PAR-2026-000002'), 'contract-tracking-2'));
        $driver = self::getContainer()->get(SimulatorDriver::class);
        \assert($driver instanceof SimulatorDriver);
        $driver->advanceAll();

        $searcher = self::getContainer()->get(FlowSearcherInterface::class);
        \assert($searcher instanceof FlowSearcherInterface);
        $fetcher = self::getContainer()->get(FlowFetcherInterface::class);
        \assert($fetcher instanceof FlowFetcherInterface);
        $parser = self::getContainer()->get(LifecycleMessageParserInterface::class);
        \assert($parser instanceof LifecycleMessageParserInterface);

        $after    = new \DateTimeImmutable('2000-01-01');
        $last     = '';
        $previous = null;
        $statuses = [];
        do {
            $page = $searcher->search(self::ORGANISATION, $after, $last, 3);
            self::assertLessThanOrEqual(3, \count($page->flows));

            foreach ($page->flows as $summary) {
                $position = [$summary->updatedAt->getTimestamp(), $summary->flowId];
                if (null !== $previous) {
                    self::assertGreaterThan(0, $position <=> $previous);
                }
                $previous = $position;

                $content = $fetcher->fetch(self::ORGANISATION, $summary->flowId);
                self::assertSame($summary->flowId, $content->flowId);
                foreach ($parser->parse($content->content) as $message) {
                    self::assertSame(self::ORGANISATION, $message->sellerSiren);
                    $statuses[$message->invoiceNumber][] = $message->statusCode;
                }

                $after = $summary->updatedAt;
                $last  = $summary->flowId;
            }
        } while ($page->hasMore);

        self::assertSame([200, 202, 203, 205], $statuses['PAR-2026-000001']);
        self::assertSame([200, 202, 203, 210], $statuses['PAR-2026-000002']);
        self::assertSame([], $searcher->search(self::ORGANISATION, $after, $last, 3)->flows);
    }

    private function submitter(): FlowSubmitterInterface
    {
        $submitter = self::getContainer()->get(FlowSubmitterInterface::class);
        \assert($submitter instanceof FlowSubmitterInterface);

        return $submitter;
    }

    private function flow(string $content, string $trackingId): OutboundFlow
    {
        return new OutboundFlow(self::ORGANISATION, $trackingId, $trackingId . '.xml', $content, hash('sha256', $content), 'CII', 'B2B');
    }
}
