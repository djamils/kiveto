<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Command\CreateSupplierReceipt;

use App\Shared\Application\Bus\CommandInterface;

final readonly class CreateSupplierReceipt implements CommandInterface
{
    /**
     * @param list<array{purchaseOrderLineId: string, articleId: string, receivedAmount: string, receivedUnit: string, lotNumber: ?string, expiryDate: ?string, manufacturedAt: ?string, actualUnitPriceMinor: ?int, actualUnitPriceCurrency: ?string, note: ?string}> $lines
     */
    public function __construct(
        public string $clinicId,
        public string $supplierId,
        public string $purchaseOrderId,
        public string $deliveryNoteReference,
        public string $matchType,
        public ?string $receivedBy,
        public ?string $comment,
        public array $lines = [],
    ) {
    }
}
