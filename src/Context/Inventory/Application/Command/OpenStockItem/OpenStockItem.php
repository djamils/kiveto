<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Command\OpenStockItem;

final readonly class OpenStockItem
{
    public function __construct(
        public string $clinicId,
        public string $articleId,
        public string $unit,
        public string $thresholdAmount,
        public string $thresholdUnit,
        public string $thresholdType,
        public bool $trackStock,
    ) {
    }
}
