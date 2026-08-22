<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Query\StockOverview;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The lots of several stock items at once — a page of stock shows them all
 * without firing a query per row.
 */
final readonly class ListLotsForStockItems implements QueryInterface
{
    /**
     * @param list<string> $stockItemIds
     */
    public function __construct(
        public string $clinicId,
        public array $stockItemIds,
    ) {
    }
}
