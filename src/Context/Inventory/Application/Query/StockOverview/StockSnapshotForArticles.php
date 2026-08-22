<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Query\StockOverview;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The figures the clinic holds for a set of articles, in one read.
 */
final readonly class StockSnapshotForArticles implements QueryInterface
{
    /**
     * @param list<string> $articleIds
     */
    public function __construct(
        public string $clinicId,
        public array $articleIds,
    ) {
    }
}
