<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Command\UpdateSupplierCatalogEntry;

use App\Shared\Application\Bus\CommandInterface;

final readonly class UpdateSupplierCatalogEntry implements CommandInterface
{
    public function __construct(
        public string $entryId,
        public string $name,
        public ?string $gtin,
        public ?string $packagingUnit,
        public ?string $packagingAmount,
    ) {
    }
}
