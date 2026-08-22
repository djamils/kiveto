<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Query\StockOverview;

use App\Shared\Application\Bus\QueryInterface;

final readonly class ListMovements implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public MovementCriteria $criteria,
    ) {
    }
}
