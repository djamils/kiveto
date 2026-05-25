<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Query\ListStockItemsByClinic;

final readonly class ListStockItemsByClinic
{
    public function __construct(
        public string $clinicId,
        public ?string $status = null,
        public int $limit = 50,
        public int $offset = 0,
    ) {
    }
}
