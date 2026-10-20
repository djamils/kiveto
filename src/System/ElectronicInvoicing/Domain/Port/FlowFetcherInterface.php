<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Domain\Port;

use App\Shared\Application\ExternalApi\ExternalServiceUnavailable;

interface FlowFetcherInterface
{
    /**
     * @throws ExternalServiceUnavailable
     */
    public function fetch(string $organisationId, string $flowId): FlowContent;
}
