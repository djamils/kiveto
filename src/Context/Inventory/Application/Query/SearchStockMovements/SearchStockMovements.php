<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Query\SearchStockMovements;

final readonly class SearchStockMovements
{
    public function __construct(
        public string $clinicId,
        public ?string $from = null,
        public ?string $to = null,
        public ?string $type = null,
        public ?string $reason = null,
        public int $limit = 50,
        public int $offset = 0,
    ) {
    }
}
