<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\SupplierCatalog\Repository;

use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\SupplierCatalog\SupplierCatalogEntry;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierCatalogEntryId;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierProductCode;

interface SupplierCatalogEntryRepositoryInterface
{
    public function save(SupplierCatalogEntry $entry): void;

    public function findById(SupplierCatalogEntryId $id): ?SupplierCatalogEntry;

    public function findBySupplierAndCode(SupplierId $supplierId, SupplierProductCode $code): ?SupplierCatalogEntry;
}
