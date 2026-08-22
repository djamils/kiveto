<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Query\Catalog\ListCatalog;

/**
 * One page of a catalogue family, with the total the pager needs.
 *
 * @template TRow of ActListRow|ArticleListRow|PackageListRow
 */
final readonly class CatalogPage
{
    /**
     * @param list<TRow> $items
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
