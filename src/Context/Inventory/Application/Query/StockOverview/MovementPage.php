<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Query\StockOverview;

final readonly class MovementPage
{
    /**
     * @param list<MovementRow> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {
    }

    public function pageCount(): int
    {
        return max(1, (int) ceil($this->total / max(1, $this->perPage)));
    }

    public function firstItem(): int
    {
        return 0 === $this->total ? 0 : ($this->page - 1) * $this->perPage + 1;
    }

    public function lastItem(): int
    {
        return min($this->total, $this->page * $this->perPage);
    }
}
