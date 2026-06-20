<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\Supplier\Repository;

use App\Context\Procurement\Domain\Supplier\Supplier;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierCode;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;

interface SupplierRepositoryInterface
{
    public function save(Supplier $supplier): void;

    public function findById(SupplierId $id): ?Supplier;

    public function findByCode(SupplierCode $code): ?Supplier;

    /** @return list<Supplier> */
    public function findAll(): array;
}
