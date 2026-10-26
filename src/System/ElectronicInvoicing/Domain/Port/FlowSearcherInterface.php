<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Domain\Port;

use App\Shared\Application\ExternalApi\ExternalServiceUnavailable;

interface FlowSearcherInterface
{
    /**
     * The organisation's flows updated strictly after ($updatedAfter,
     * $afterFlowId), ordered by (updatedAt, flowId).
     *
     * @throws ExternalServiceUnavailable
     */
    public function search(string $organisationId, \DateTimeImmutable $updatedAfter, string $afterFlowId, int $limit): FlowPage;
}
