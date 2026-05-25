<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Query\SearchStockMovements;

final readonly class StockMovementView
{
    public function __construct(
        public string $id,
        public string $clinicId,
        public string $articleId,
        public ?string $lotId,
        public string $type,
        public string $reason,
        public string $quantityAmount,
        public string $quantityUnit,
        public string $occurredAt,
        public ?string $reference,
        public ?string $performedBy,
        public ?string $note,
    ) {
    }
}
