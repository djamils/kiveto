<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Query\OrderBook;

/**
 * A purchase order that is not finished: still being filled (DRAFT), being
 * sent, sent and waiting for its delivery, or partly delivered. A supplier
 * that takes its orders by file has the order exported: `exportFile` names
 * the stored file to hand over.
 */
final readonly class OpenPurchaseOrder
{
    /**
     * @param list<OpenPurchaseOrderLine> $lines
     */
    public function __construct(
        public string $id,
        public string $orderNumber,
        public string $supplierId,
        public string $supplierName,
        public string $supplierAccountId,
        public string $status,
        public string $currency,
        public ?string $submittedAt,
        public string $createdAt,
        public array $lines,
        public ?string $exportFile = null,
    ) {
    }

    public function isDraft(): bool
    {
        return 'DRAFT' === $this->status;
    }
}
