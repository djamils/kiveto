<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\SupplierAccount\Repository;

use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\SupplierAccount\SupplierAccount;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\SupplierAccountId;

interface SupplierAccountRepositoryInterface
{
    public function save(SupplierAccount $account): void;

    public function findById(SupplierAccountId $id): ?SupplierAccount;

    public function findByClinicAndSupplier(ClinicId $clinicId, SupplierId $supplierId): ?SupplierAccount;
}
