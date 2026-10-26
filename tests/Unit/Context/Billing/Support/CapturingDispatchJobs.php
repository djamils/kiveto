<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Support;

use App\Context\Billing\Application\Port\DispatchJobsInterface;

final class CapturingDispatchJobs implements DispatchJobsInterface
{
    /** @var list<string> */
    public array $emails = [];

    public function sendInvoiceEmail(string $dispatchId): void
    {
        $this->emails[] = $dispatchId;
    }
}
