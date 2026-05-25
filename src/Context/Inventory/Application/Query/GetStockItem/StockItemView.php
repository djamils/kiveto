<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Query\GetStockItem;

final readonly class StockItemView
{
    /**
     * @param list<LotView> $lots
     */
    public function __construct(
        public string $id,
        public string $clinicId,
        public string $articleId,
        public string $totalOnHandAmount,
        public string $totalOnHandUnit,
        public string $availableQuantityAmount,
        public string $thresholdAmount,
        public string $thresholdUnit,
        public string $thresholdType,
        public bool $trackStock,
        public string $status,
        public array $lots,
    ) {
    }
}
