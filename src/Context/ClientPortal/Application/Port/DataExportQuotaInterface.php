<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

use App\Context\ClientPortal\Domain\Exception\DataExportRateLimitedException;

/** One data export per account per 24 hours, kept outside any transaction. */
interface DataExportQuotaInterface
{
    /** @throws DataExportRateLimitedException when the account already used its export */
    public function consume(string $userId): void;

    /** Gives the export back (its build failed): a new request is accepted at once. */
    public function refund(string $userId): void;
}
