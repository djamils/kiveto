<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Infrastructure\Platform\Disabled;

use App\System\ElectronicInvoicing\Domain\Port\DirectoryEntry;
use App\System\ElectronicInvoicing\Domain\Port\DirectoryLookupInterface;
use App\System\ElectronicInvoicing\Domain\Port\FlowContent;
use App\System\ElectronicInvoicing\Domain\Port\FlowFetcherInterface;
use App\System\ElectronicInvoicing\Domain\Port\FlowPage;
use App\System\ElectronicInvoicing\Domain\Port\FlowSearcherInterface;
use App\System\ElectronicInvoicing\Domain\Port\FlowSubmitterInterface;
use App\System\ElectronicInvoicing\Domain\Port\OutboundFlow;
use App\System\ElectronicInvoicing\Domain\Port\SubmissionReceipt;

/**
 * No platform configured (the production default until a real adapter
 * exists). Never reached: no connection can be opened, so nothing is ever
 * submitted or polled — documents wait, prepared.
 */
final class DisabledPlatform implements FlowSubmitterInterface, FlowSearcherInterface, FlowFetcherInterface, DirectoryLookupInterface
{
    public function submit(OutboundFlow $flow): SubmissionReceipt
    {
        throw self::disabled();
    }

    public function search(string $organisationId, \DateTimeImmutable $updatedAfter, string $afterFlowId, int $limit): FlowPage
    {
        throw self::disabled();
    }

    public function fetch(string $organisationId, string $flowId): FlowContent
    {
        throw self::disabled();
    }

    public function lookup(string $siren): DirectoryEntry
    {
        throw self::disabled();
    }

    private static function disabled(): \LogicException
    {
        return new \LogicException('Electronic invoicing is disabled.');
    }
}
