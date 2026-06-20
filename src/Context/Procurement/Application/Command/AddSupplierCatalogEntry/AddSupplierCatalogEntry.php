<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Command\AddSupplierCatalogEntry;

use App\Shared\Application\Bus\CommandInterface;

final readonly class AddSupplierCatalogEntry implements CommandInterface
{
    public function __construct(
        public string $supplierId,
        public string $productCode,
        public string $name,
        public ?string $gtin,
        public int $catalogPriceMinor,
        public string $catalogPriceCurrency,
        public string $catalogPriceValidFrom,
        public ?string $catalogPriceValidTo,
        public ?string $packagingUnit,
        public ?string $packagingAmount,
    ) {
    }
}
