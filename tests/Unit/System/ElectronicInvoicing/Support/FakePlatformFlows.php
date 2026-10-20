<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\ElectronicInvoicing\Support;

use App\System\ElectronicInvoicing\Domain\Port\FlowContent;
use App\System\ElectronicInvoicing\Domain\Port\FlowFetcherInterface;
use App\System\ElectronicInvoicing\Domain\Port\FlowPage;
use App\System\ElectronicInvoicing\Domain\Port\FlowSearcherInterface;
use App\System\ElectronicInvoicing\Domain\Port\FlowSummary;

/**
 * Flows listed in (updatedAt, flowId) order; their content is what parsing
 * keys on in the tests.
 */
final class FakePlatformFlows implements FlowSearcherInterface, FlowFetcherInterface
{
    public int $searches = 0;

    /**
     * @param list<array{string, \DateTimeImmutable, string}> $flows id, updatedAt, content
     */
    public function __construct(public array $flows = [])
    {
    }

    public function search(string $organisationId, \DateTimeImmutable $updatedAfter, string $afterFlowId, int $limit): FlowPage
    {
        ++$this->searches;
        $after = array_values(array_filter(
            $this->flows,
            static fn (array $f): bool => $f[1] > $updatedAfter || ($f[1] === $updatedAfter && strcmp($f[0], $afterFlowId) > 0),
        ));

        return new FlowPage(
            array_map(static fn (array $f): FlowSummary => new FlowSummary($f[0], $f[1]), \array_slice($after, 0, $limit)),
            \count($after) > $limit,
        );
    }

    public function fetch(string $organisationId, string $flowId): FlowContent
    {
        foreach ($this->flows as [$id, , $content]) {
            if ($id === $flowId) {
                return new FlowContent($id, $content);
            }
        }

        return new FlowContent($flowId, 'unknown');
    }
}
