<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Infrastructure\Platform\Simulator;

use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\ElectronicInvoicing\Domain\Connection\SyncCursor;
use App\System\ElectronicInvoicing\Domain\Port\DirectoryEntry;
use App\System\ElectronicInvoicing\Domain\Port\DirectoryLookupInterface;
use App\System\ElectronicInvoicing\Domain\Port\FlowContent;
use App\System\ElectronicInvoicing\Domain\Port\FlowFetcherInterface;
use App\System\ElectronicInvoicing\Domain\Port\FlowPage;
use App\System\ElectronicInvoicing\Domain\Port\FlowSearcherInterface;
use App\System\ElectronicInvoicing\Domain\Port\FlowSubmitterInterface;
use App\System\ElectronicInvoicing\Domain\Port\FlowSummary;
use App\System\ElectronicInvoicing\Domain\Port\OutboundFlow;
use App\System\ElectronicInvoicing\Domain\Port\SubmissionReceipt;
use App\System\ElectronicInvoicing\Infrastructure\Format\XsdValidator;

/**
 * A local approved platform (dev, test, demo — never production): no
 * network, deterministic scenarios chosen by the buyer SIREN
 * (SimulationScenario).
 *
 * Deposited files are XSD-validated: an invalid file is inadmissible (501
 * IRR_SYNTAX). Each status the platform emits is listed by search() as a
 * flow of its own (`<deposit flow id>-<nn>`, updated at the status time) and
 * fetch() returns the CDAR lifecycle message of that one status, so statuses
 * reach Kiveto through the real parser and the real synchronisation.
 */
final readonly class LocalPlatformSimulator implements FlowSubmitterInterface, FlowSearcherInterface, FlowFetcherInterface, DirectoryLookupInterface
{
    public const string ADDRESS_SCHEME = '0225';

    public function __construct(
        private SimulatedFlowStoreInterface $flows,
        private XsdValidator $validator,
        private string $schemaPath,
        private SimulatedCdarWriter $writer,
        private SimulationScheduler $scheduler,
        private UuidGeneratorInterface $uuids,
        private ClockInterface $clock,
    ) {
    }

    public function submit(OutboundFlow $flow): SubmissionReceipt
    {
        $known = $this->flows->findByDeposit($flow->trackingId, $flow->sha256);

        if (null !== $known) {
            return new SubmissionReceipt($known->flowId, $known->createdAt);
        }

        $document = DepositedDocument::read($flow->content);
        $scenario = [] === $this->validator->validate($flow->content, $this->schemaPath)
            ? SimulationScenario::forBuyerSiren($document->buyerSiren)
            : SimulationScenario::INADMISSIBLE;

        $deposited = SimulatedFlow::deposit($this->uuids->generate(), $flow, $document, $scenario, $this->clock->now());
        $this->flows->save($deposited);
        $this->scheduler->scheduleNextStep($deposited);

        return new SubmissionReceipt($deposited->flowId, $deposited->createdAt);
    }

    public function search(string $organisationId, \DateTimeImmutable $updatedAfter, string $afterFlowId, int $limit): FlowPage
    {
        $cursor  = SyncCursor::at($updatedAfter, $afterFlowId);
        $entries = [];

        foreach ($this->flows->forOrganisation($organisationId) as $flow) {
            foreach ($flow->statuses() as $index => $status) {
                $entry = new FlowSummary($flow->statusFlowId($index), $status->at);

                if ($cursor->precedes($entry->updatedAt, $entry->flowId)) {
                    $entries[] = $entry;
                }
            }
        }

        usort($entries, static fn (FlowSummary $a, FlowSummary $b): int => [$a->updatedAt, $a->flowId] <=> [$b->updatedAt, $b->flowId]);

        return new FlowPage(\array_slice($entries, 0, max(0, $limit)), \count($entries) > $limit);
    }

    public function fetch(string $organisationId, string $flowId): FlowContent
    {
        $flow   = $this->flows->find(SimulatedFlow::depositFlowId($flowId));
        $status = $flow?->statusFor($flowId);

        if (null === $flow || null === $status || $flow->organisationId !== $organisationId) {
            throw new \RuntimeException(\sprintf('Unknown flow "%s" for organisation %s.', $flowId, $organisationId));
        }

        return new FlowContent($flowId, $this->writer->write($flow, $status));
    }

    public function lookup(string $siren): DirectoryEntry
    {
        if (SimulationScenario::BUYER_NOT_TRANSMITTED === $siren) {
            return new DirectoryEntry(false, null, null);
        }

        return new DirectoryEntry(true, self::ADDRESS_SCHEME, $siren);
    }
}
