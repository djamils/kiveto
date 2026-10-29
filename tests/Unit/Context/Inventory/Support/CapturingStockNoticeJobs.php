<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Inventory\Support;

use App\Context\Inventory\Application\Port\StockNoticeJobsInterface;

/**
 * Remembers the bell jobs queued.
 */
final class CapturingStockNoticeJobs implements StockNoticeJobsInterface
{
    /** @var list<array{string, string}> clinic id, alert id */
    public array $criticalStock = [];

    /** @var list<string> clinic ids */
    public array $expiringLots = [];

    public function notifyCriticalStock(string $clinicId, string $alertId): void
    {
        $this->criticalStock[] = [$clinicId, $alertId];
    }

    public function notifyExpiringLots(string $clinicId): void
    {
        $this->expiringLots[] = $clinicId;
    }
}
