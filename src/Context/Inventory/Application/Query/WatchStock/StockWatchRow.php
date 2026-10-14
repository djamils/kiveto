<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Query\WatchStock;

use App\Context\Inventory\Domain\StockAlert\ValueObject\StockAlertSeverity;

/**
 * One point of attention. `quantity` is the lot's for a lot, what is usable
 * for the article otherwise; `days` counts down to a lot's expiry (negative
 * once passed).
 */
final readonly class StockWatchRow
{
    public function __construct(
        public StockWatchKind $kind,
        public StockAlertSeverity $severity,
        public string $stockItemId,
        public string $articleId,
        public string $quantity,
        public string $threshold,
        public string $unit,
        public ?string $lotId = null,
        public ?string $lotNumber = null,
        public ?string $expiryDate = null,
        public ?int $days = null,
        public ?string $recallReason = null,
        public ?string $recalledAt = null,
    ) {
    }
}
