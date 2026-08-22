<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\SearchInvoices;

/**
 * One page of results plus what the pager needs to draw itself.
 */
final readonly class InvoiceListItemView
{
    /**
     * @param list<InvoiceListRow> $items
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
}
