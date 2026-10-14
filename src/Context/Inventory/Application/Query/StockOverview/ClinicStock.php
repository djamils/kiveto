<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Query\StockOverview;

final readonly class ClinicStock
{
    /**
     * @param array<string, StockSnapshotRow>  $items     keyed by article id
     * @param array<string, list<LotRow>>      $lots      keyed by stock item id
     * @param array<string, OutflowRow>        $outflows  keyed by article id
     * @param array<string, list<MovementRow>> $movements the latest of each article, keyed by article id
     */
    public function __construct(
        public array $items,
        public array $lots,
        public array $outflows,
        public array $movements,
        public ?\DateTimeImmutable $lastCountAt,
    ) {
    }
}
