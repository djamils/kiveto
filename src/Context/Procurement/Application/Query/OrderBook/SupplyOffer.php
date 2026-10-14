<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Query\OrderBook;

/**
 * What a supplier the clinic buys from sells under a barcode or a reference:
 * the catalogue entry to order, the price the clinic pays (its negotiated one
 * when it has one) and how it comes packed — `packagingAmount` units of the
 * article in one `packagingUnit`.
 */
final readonly class SupplyOffer
{
    public function __construct(
        public string $supplierId,
        public string $supplierName,
        public string $supplierAccountId,
        public string $catalogEntryId,
        public string $productCode,
        public ?string $gtin,
        public string $name,
        public int $priceMinor,
        public string $currency,
        public ?string $packagingUnit,
        public ?string $packagingAmount,
    ) {
    }
}
