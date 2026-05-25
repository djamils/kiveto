<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Command\ChangeStockThreshold;

final readonly class ChangeStockThreshold
{
    public function __construct(
        public string $stockItemId,
        public string $clinicId,
        public string $thresholdAmount,
        public string $thresholdUnit,
        public string $thresholdType = 'ABSOLUTE',
    ) {
    }
}
