<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Dto;

final readonly class DeliveryNoteData
{
    /**
     * @param list<array{articleCode: string, qty: string, unit: string, lotNumber: ?string, expiryDate: ?string, actualPrice: ?int}> $lines
     */
    public function __construct(
        public readonly string $deliveryNoteReference,
        public readonly string $purchaseOrderExternalReference,
        public readonly array $lines,
    ) {
    }
}
