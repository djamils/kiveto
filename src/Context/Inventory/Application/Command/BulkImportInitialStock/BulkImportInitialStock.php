<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Command\BulkImportInitialStock;

final readonly class BulkImportInitialStock
{
    /**
     * @param list<array{
     *     clinicId: string,
     *     articleId: string,
     *     unit: string,
     *     thresholdAmount: string,
     *     thresholdUnit: string,
     *     thresholdType: string,
     *     trackStock: bool,
     *     lots: list<array{
     *         lotNumber: string,
     *         quantityAmount: string,
     *         quantityUnit: string,
     *         expiryDate: string,
     *         sourceSupplierId?: string
     *     }>
     * }> $items
     */
    public function __construct(
        public array $items,
    ) {
    }
}
