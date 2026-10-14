<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Query\OrderBook;

final readonly class OpenPurchaseOrderLine
{
    public function __construct(
        public string $id,
        public string $articleId,
        public string $catalogEntryId,
        public string $orderedAmount,
        public string $orderedUnit,
        public string $receivedAmount,
        public int $unitPriceMinor,
        public string $status,
    ) {
    }
}
