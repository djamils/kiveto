<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Domain\Port;

use App\Shared\Application\ExternalApi\ExternalServiceUnavailable;

interface FlowSubmitterInterface
{
    /**
     * Deposits a file. Idempotent on (trackingId, sha256): the same document
     * submitted again answers the same flow id.
     *
     * @throws ExternalServiceUnavailable
     */
    public function submit(OutboundFlow $flow): SubmissionReceipt;
}
