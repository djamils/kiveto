<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Dto;

final readonly class CatalogEntryData
{
    public function __construct(
        public readonly string $supplierProductCode,
        public readonly string $name,
        public readonly ?string $gtin,
        public readonly int $priceMinor,
        public readonly string $currency,
        public readonly ?string $unit,
        public readonly ?string $packagingAmount,
    ) {
    }
}
