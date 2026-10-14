<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Query\StockOverview;

/**
 * How fast an article leaves the shelf: what went out each week (oldest week
 * first, the current one last) and what was used — consultations and counter
 * sales, not losses — over the last thirty days.
 */
final readonly class OutflowRow
{
    /**
     * @param list<string> $weekly decimal strings
     */
    public function __construct(
        public string $articleId,
        public array $weekly,
        public string $usedLastThirtyDays,
    ) {
    }
}
