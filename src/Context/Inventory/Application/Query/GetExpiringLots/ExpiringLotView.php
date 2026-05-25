<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Query\GetExpiringLots;

final readonly class ExpiringLotView
{
    public function __construct(
        public string $lotId,
        public string $stockItemId,
        public string $clinicId,
        public string $articleId,
        public string $lotNumber,
        public string $quantityAmount,
        public string $quantityUnit,
        public string $expiryDate,
        public int $daysUntilExpiry,
    ) {
    }
}
