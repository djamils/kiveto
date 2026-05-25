<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Query\ListStockItemsByClinic;

final readonly class StockItemSummaryView
{
    public function __construct(
        public string $id,
        public string $clinicId,
        public string $articleId,
        public string $totalOnHandAmount,
        public string $totalOnHandUnit,
        public string $availableQuantityAmount,
        public string $thresholdAmount,
        public string $thresholdUnit,
        public bool $trackStock,
        public string $status,
        public int $activeLotCount,
    ) {
    }
}
