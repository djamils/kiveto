<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Query\GetStockMovementHistory;

final readonly class GetStockMovementHistory
{
    public function __construct(
        public string $clinicId,
        public ?string $articleId = null,
        public ?string $from = null,
        public ?string $to = null,
        public int $limit = 100,
        public int $offset = 0,
    ) {
    }
}
