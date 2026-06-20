<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\SupplierPricing\Repository;

use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierCatalogEntryId;
use App\Context\Procurement\Domain\SupplierPricing\SupplierPricing;
use App\Context\Procurement\Domain\SupplierPricing\ValueObject\SupplierPricingId;

interface SupplierPricingRepositoryInterface
{
    public function save(SupplierPricing $pricing): void;

    public function findById(SupplierPricingId $id): ?SupplierPricing;

    public function findByClinicAndEntry(ClinicId $clinicId, SupplierCatalogEntryId $entryId): ?SupplierPricing;

    public function remove(SupplierPricing $pricing): void;
}
