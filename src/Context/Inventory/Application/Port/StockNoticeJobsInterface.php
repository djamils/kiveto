<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Port;

/**
 * Bell jobs run by a worker, queued in the transaction that raised them.
 */
interface StockNoticeJobsInterface
{
    /** NotifyCriticalStock, run as soon as possible. */
    public function notifyCriticalStock(string $clinicId, string $alertId): void;

    /** NotifyExpiringLots of one clinic. */
    public function notifyExpiringLots(string $clinicId): void;
}
