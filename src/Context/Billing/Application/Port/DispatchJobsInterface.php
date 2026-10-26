<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Port;

/**
 * Queues the asynchronous part of a dispatch: the user-facing commands only
 * validate, log and enqueue, the PDF is rendered by a worker (AC 25).
 */
interface DispatchJobsInterface
{
    /** SendInvoiceEmail, run by a worker. */
    public function sendInvoiceEmail(string $dispatchId): void;
}
