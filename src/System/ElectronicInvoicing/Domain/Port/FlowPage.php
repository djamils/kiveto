<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Domain\Port;

final readonly class FlowPage
{
    /**
     * @param list<FlowSummary> $flows ordered by (updatedAt, flowId)
     */
    public function __construct(
        public array $flows,
        public bool $hasMore,
    ) {
    }
}
